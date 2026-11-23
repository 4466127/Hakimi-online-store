<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Models\Seller;
use App\Models\SellerCommissionRate;
use App\Models\SellerOrder;
use App\Models\SellerSettlement;
use App\Models\SellerTransaction;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use App\Support\Money;
use App\Support\Num;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * کمیسیون بازارک و حساب فروشندگان.
 *
 * قاعده‌ها (صاحب پروژه، ۲۶ عقرب ۱۴۰۵):
 * - ثبت‌نام و گذاشتن جنس رایگان؛ فیس ماهانه نیست.
 * - کمیسیون فقط روی سفارش **تحویل‌شده و تمام‌شده** ثبت می‌شود (recordSale را مرحله‌ی ۴ هنگام «تحویل شد» صدا می‌زند)؛
 *   سفارش لغوشده یا تحویل‌نشده هیچ سندی ندارد.
 * - جنس برگشتی ← سند برگشت (recordReversal) با **همان نرخ اصلی**.
 * - کرایه‌ی تحویل در حساب کمیسیون نیست.
 * - نرخ: نرخ ویژه‌ی فروشنده ← نرخ دسته (یا نزدیک‌ترین بالادست) ← پیش‌فرض تنظیمات. هیچ نرخی در کد نیست.
 * - نرخ و مبلغ همان لحظه در سند ذخیره می‌شود؛ تغییر نرخ بعدی روی گذشته اثر ندارد.
 * - هر فروش فقط یک بار (کلید یکتا)؛ همه‌ی حساب‌ها با bcmath (بی float) و داخل transaction با قفل ردیف فروشنده.
 * - فروش خود بازارک (بی فروشنده) جدا ثبت می‌شود: seller_id خالی، کمیسیون ۰.
 */
class CommissionService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $logger,
        private readonly SellerLedger $ledger,
    ) {}

    // ───────────────────────── نرخ‌ها ─────────────────────────

    /** نرخ پیش‌فرض («سایر اجناس») از تنظیمات. */
    public function defaultRate(): string
    {
        return Num::round((string) $this->settings->get('marketplace.default_commission_rate'), 2);
    }

    /** نرخ یک دسته: خودش، یا نزدیک‌ترین بالادستی که نرخ دارد، یا پیش‌فرض. */
    public function categoryRate(?Category $category): AppliedRate
    {
        if ($category === null) {
            return new AppliedRate($this->defaultRate(), 'default');
        }

        $chainIds = [...$category->ancestorIds(), $category->id];                // ریشه … خود
        $rates = Category::withTrashed()->whereIn('id', $chainIds)->pluck('commission_rate', 'id');

        foreach (array_reverse($chainIds) as $id) {                              // خود ← ریشه
            $rate = $rates[$id] ?? null;
            if ($rate !== null) {
                return new AppliedRate(Num::round((string) $rate, 2), 'category', (int) $id);
            }
        }

        return new AppliedRate($this->defaultRate(), 'default');
    }

    /**
     * نرخ مؤثر همه‌ی دسته‌ها با یک query (برای لیست پنل و صفحه‌ی نرخ‌ها).
     *
     * @return array<int, AppliedRate>
     */
    public function allCategoryRates(): array
    {
        $rows = Category::query()->get(['id', 'parent_id', 'commission_rate'])->keyBy('id');
        $default = $this->defaultRate();
        $out = [];

        $resolve = function (int $id) use (&$resolve, &$out, $rows, $default): AppliedRate {
            if (isset($out[$id])) {
                return $out[$id];
            }
            $row = $rows->get($id);
            if ($row === null) {
                return new AppliedRate($default, 'default');
            }
            if ($row->commission_rate !== null) {
                return $out[$id] = new AppliedRate(Num::round((string) $row->commission_rate, 2), 'category', $id);
            }

            return $out[$id] = $row->parent_id ? $resolve((int) $row->parent_id) : new AppliedRate($default, 'default');
        };

        foreach ($rows->keys() as $id) {
            $resolve((int) $id);
        }

        return $out;
    }

    /**
     * جدول نرخ‌ها برای سایت: هر دسته‌ی اصلی با نرخش، و گروه‌هایی که نرخ متفاوت دارند زیر آن.
     *
     * @param  list<array<string, mixed>>  $tree  از CategoryMenu::tree()
     * @return list<array{name: string, rate: string, children: list<array{name: string, rate: string}>}>
     */
    public function publicRateTable(array $tree): array
    {
        $rates = $this->allCategoryRates();
        $rows = [];
        foreach ($tree as $root) {
            $rootRate = $rates[$root['id']]->rate ?? $this->defaultRate();
            $children = [];
            foreach ($root['children'] as $group) {
                $groupRate = $rates[$group['id']]->rate ?? $rootRate;
                if (Money::compare($groupRate, $rootRate) !== 0) {
                    $children[] = ['name' => $group['name'], 'rate' => $groupRate];
                }
            }
            $rows[] = ['name' => $root['name'], 'rate' => $rootRate, 'children' => $children];
        }

        return $rows;
    }

    /**
     * نرخ‌های ویژه‌ی فعال یک فروشنده (برای صفحه‌ی وضعیت فروشنده).
     *
     * @return Collection<int, SellerCommissionRate>
     */
    public function activeOverrides(Seller $seller): Collection
    {
        return SellerCommissionRate::query()->with('category')
            ->where('seller_id', $seller->id)->activeAt(now())->orderBy('rate')->get();
    }

    /**
     * قاعده‌ای که برای فروش این جنس (از این فروشنده، در این دسته) به کار می‌رود.
     *
     * اولویت (تصمیم ۳ صاحب پروژه، ۱ قوس ۱۴۰۵):
     *   ۱. «استثنا»ی فعال فروشنده (مزایای شروع، توافق ویژه با تاریخ پایان) — بر همه غلبه می‌کند
     *   ۲. قاعده‌ی فعال همان جنس
     *   ۳. نرخ دسته (یا نزدیک‌ترین بالادستی که نرخ دارد)
     *   ۴. نرخ عادی فروشنده (اول مخصوص همان دسته یا بالادستش، بعد «همه‌ی دسته‌ها»)
     *   ۵. پیش‌فرض تنظیمات بازارگاه
     * جنس خود بازارک (بی فروشنده) همیشه ۰ است. هیچ نرخی در کد نیست و هیچ نرخی از مرورگر گرفته نمی‌شود.
     */
    public function rateFor(?Seller $seller, ?Category $category, ?DateTimeInterface $at = null, ?Product $product = null): AppliedRate
    {
        if ($seller === null) {
            return new AppliedRate('0.00', 'platform', $category?->id);
        }

        $at ??= now();
        $overrides = SellerCommissionRate::query()
            ->where('seller_id', $seller->id)
            ->activeAt($at)
            ->orderByDesc('id')
            ->get();
        $chain = $category ? array_reverse([...$category->ancestorIds(), $category->id]) : []; // خود ← ریشه

        // ۱) استثنا
        $exception = $this->matchOverride($overrides->where('is_exception', true), $chain);
        if ($exception) {
            return $this->fromOverride($exception, 'exception', $category?->id);
        }

        // ۲) قاعده‌ی جنس
        if ($product !== null) {
            $rule = ProductCommissionRate::query()->where('product_id', $product->id)->activeAt($at)->orderByDesc('id')->first();
            if ($rule) {
                return new AppliedRate(
                    $rule->type === AppliedRate::FIXED ? '0.00' : Num::round((string) $rule->rate, 2),
                    'product', $category?->id, $rule->id, $rule->ends_at,
                    $rule->type === AppliedRate::FIXED ? AppliedRate::FIXED : AppliedRate::PERCENT,
                    $rule->type === AppliedRate::FIXED ? Money::of((string) $rule->fixed_amount) : null,
                );
            }
        }

        // ۳) دسته
        $categoryRate = $this->categoryRate($category);
        if ($categoryRate->source === 'category') {
            return new AppliedRate($categoryRate->rate, 'category', $category?->id);
        }

        // ۴) نرخ عادی فروشنده
        $regular = $this->matchOverride($overrides->where('is_exception', false), $chain);
        if ($regular) {
            return $this->fromOverride($regular, 'seller', $category?->id);
        }

        // ۵) پیش‌فرض
        return new AppliedRate($categoryRate->rate, 'default', $category?->id);
    }

    /**
     * نرخ ویژه‌ی فروشنده که به این دسته می‌خورد: اول مخصوص همین دسته یا نزدیک‌ترین بالادست، بعد «همه‌ی دسته‌ها».
     *
     * @param  Collection<int, SellerCommissionRate>  $overrides
     * @param  list<int>  $chain  خود ← ریشه
     */
    private function matchOverride(Collection $overrides, array $chain): ?SellerCommissionRate
    {
        foreach ($chain as $categoryId) {
            $match = $overrides->firstWhere('category_id', $categoryId);
            if ($match) {
                return $match;
            }
        }

        return $overrides->firstWhere('category_id', null);
    }

    private function fromOverride(SellerCommissionRate $o, string $source, ?int $categoryId): AppliedRate
    {
        $fixed = $o->type === AppliedRate::FIXED;

        return new AppliedRate(
            $fixed ? '0.00' : Num::round((string) $o->rate, 2),
            $source, $categoryId, $o->id, $o->ends_at,
            $fixed ? AppliedRate::FIXED : AppliedRate::PERCENT,
            $fixed ? Money::of((string) $o->fixed_amount) : null,
        );
    }

    // ───────────────────────── ثبت فروش و برگشت ─────────────────────────

    /**
     * کمیسیون یک ردیف فروش **تحویل‌شده** را ثبت و سهم فروشنده را به حسابش اضافه می‌کند.
     * اگر همین ردیف قبلاً ثبت شده بود، همان سند قبلی برمی‌گردد (هیچ چیز دو بار ثبت نمی‌شود).
     */
    public function recordSale(SaleLine $line, ?User $actor = null): CommissionEntry
    {
        $gross = Money::of($line->itemsAmount);                // کرایه عمداً نیست
        if (Money::compare($gross, 0) < 0) {
            throw new InvalidArgumentException('Sale amount cannot be negative.');
        }

        $existing = CommissionEntry::query()->where('idempotency_key', $line->idempotencyKey())->first();
        if ($existing) {
            return $existing;
        }

        $occurredAt = $line->occurredAt ?? now();
        $rate = $this->rateFor($line->seller, $line->category, $occurredAt);
        $commission = $line->seller ? $rate->commissionFor($gross, 1) : '0.00';
        $sellerAmount = $line->seller ? Money::sub($gross, $commission) : '0.00';

        try {
            return DB::transaction(function () use ($line, $gross, $rate, $commission, $sellerAmount, $occurredAt, $actor): CommissionEntry {
                $entry = CommissionEntry::create([
                    'idempotency_key' => $line->idempotencyKey(),
                    'seller_id' => $line->seller?->id,
                    'kind' => CommissionEntry::KIND_SALE,
                    'source_type' => $line->sourceType,
                    'source_id' => $line->sourceId,
                    'category_id' => $line->category?->id,
                    'description' => $line->description,
                    'gross_amount' => $gross,
                    'commission_rate' => $rate->rate,
                    'commission_type' => $rate->type,
                    'rate_source' => $rate->source,
                    'commission_amount' => $commission,
                    'seller_amount' => $sellerAmount,
                    'occurred_at' => $occurredAt,
                    'created_by' => $actor?->id,
                ]);

                if ($line->seller) {
                    $this->ledger->append($line->seller, 'earning', $sellerAmount, [
                        'commission_entry_id' => $entry->id,
                        'description' => $line->description,
                        'created_by' => $actor?->id,
                    ]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException $e) {
            // دو درخواست همزمان: دومی سند اولی را می‌گیرد
            return CommissionEntry::query()->where('idempotency_key', $line->idempotencyKey())->firstOrFail();
        }
    }

    /**
     * طلب یک خط سفارش **تحویل‌شده و پول‌رسیده** را از روی **کپی قاعده در همان خط** ثبت می‌کند
     * (نرخ، مبلغ کمیسیون و سهم فروشنده هنگام ثبت سفارش ثابت شده‌اند؛ اینجا دوباره حساب نمی‌شود).
     * پایه = مبلغ مشمول کمیسیون (مبلغ خط − تخفیف فروشنده)؛ کرایه هیچ‌وقت نیست.
     * تکرار، همان سند قبلی را برمی‌گرداند (کلید یکتای order_item:{id}:sale).
     *
     * @param  DateTimeInterface|null  $eligibleAt  خالی = هنوز قابل تسویه نیست (پول دست پیک)
     */
    public function recordOrderItem(OrderItem $item, SellerOrder $sellerOrder, ?Payment $payment, ?DateTimeInterface $eligibleAt, ?User $actor = null, string $description = ''): CommissionEntry
    {
        $key = 'order_item:'.$item->id.':sale';
        $existing = CommissionEntry::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $seller = $sellerOrder->seller_id ? Seller::query()->find($sellerOrder->seller_id) : null;
        $gross = Money::sub($item->line_total, $item->seller_discount);
        $commission = $seller ? Money::of($item->commission_amount) : '0.00';
        $sellerAmount = $seller ? Money::of($item->seller_amount) : '0.00';

        try {
            return DB::transaction(function () use ($item, $sellerOrder, $payment, $eligibleAt, $actor, $description, $key, $seller, $gross, $commission, $sellerAmount): CommissionEntry {
                $entry = CommissionEntry::create([
                    'idempotency_key' => $key,
                    'seller_id' => $seller?->id,
                    'kind' => CommissionEntry::KIND_SALE,
                    'source_type' => 'order_item',
                    'source_id' => $item->id,
                    'order_id' => $item->order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'category_id' => $item->category_id,
                    'description' => $description,
                    'gross_amount' => $gross,
                    // سهم تخفیف کد بازارک همین خط: هزینه‌ی بازارک، جدا از کمیسیون و طلب فروشنده (مرحله‌ی ۹-۳)
                    'platform_discount' => Money::of((string) $item->platform_discount),
                    'commission_rate' => Num::round((string) $item->commission_rate, 2),
                    'commission_type' => $item->commission_type,
                    'rate_source' => $item->rate_source,
                    'commission_amount' => $commission,
                    'seller_amount' => $sellerAmount,
                    'currency_code' => $sellerOrder->order?->currency_code ?? 'AFN',
                    'occurred_at' => now(),
                    'created_by' => $actor?->id,
                ]);
                OrderItem::query()->whereKey($item->id)->toBase()->update(['commission_entry_id' => $entry->id]);

                if ($seller) {
                    $this->ledger->append($seller, 'earning', $sellerAmount, [
                        'commission_entry_id' => $entry->id,
                        'order_id' => $item->order_id,
                        'seller_order_id' => $sellerOrder->id,
                        'payment_id' => $payment?->id,
                        'eligible_at' => $eligibleAt,
                        'description' => $description,
                        'created_by' => $actor?->id,
                    ]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException $e) {
            return CommissionEntry::query()->where('idempotency_key', $key)->firstOrFail();
        }
    }

    /**
     * برگشت تمام یا بخشی از یک فروش (جنس مرجوع‌شده). کمیسیون با **نرخ همان فروش** برگشت داده می‌شود
     * و از حساب فروشنده کم می‌شود. $key یکتاست (مثلاً «order_return:7»)؛ تکرار، سند قبلی را برمی‌گرداند.
     *
     * @param  string|null  $amount  مبلغ جنس برگشتی (بی کرایه)؛ null = همه‌ی باقی‌مانده
     */
    public function recordReversal(CommissionEntry $sale, string $key, ?string $amount = null, ?string $description = null, ?User $actor = null): CommissionEntry
    {
        if ($sale->kind !== CommissionEntry::KIND_SALE) {
            throw new InvalidArgumentException('Only a sale can be reversed.');
        }
        $idempotencyKey = $key.':reversal';
        $existing = CommissionEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($sale, $idempotencyKey, $amount, $description, $actor): CommissionEntry {
                // قفل سند اصلی تا دو برگشت همزمان از مبلغ باقی‌مانده بیشتر نشود
                CommissionEntry::query()->whereKey($sale->id)->lockForUpdate()->first();
                $reversed = CommissionEntry::query()->where('reverses_id', $sale->id);
                $remainingGross = Money::add($sale->gross_amount, (string) (clone $reversed)->sum('gross_amount'));
                $remainingCommission = Money::add($sale->commission_amount, (string) (clone $reversed)->sum('commission_amount'));
                $remainingSeller = Money::add($sale->seller_amount, (string) (clone $reversed)->sum('seller_amount'));
                $remainingDiscount = Money::add($sale->platform_discount, (string) (clone $reversed)->sum('platform_discount'));

                $gross = $amount === null ? $remainingGross : Money::of($amount);
                if (Money::compare($gross, 0) <= 0 || Money::compare($gross, $remainingGross) > 0) {
                    throw new InvalidArgumentException('Reversal amount must be positive and not more than what is left.');
                }

                // برگشت کامل باقی‌مانده: دقیقاً همان مبلغ‌ها (بی خطای گرد کردن)
                if (Money::compare($gross, $remainingGross) === 0) {
                    $commission = $remainingCommission;
                    $sellerAmount = $remainingSeller;
                    $platformDiscount = $remainingDiscount;
                } else {
                    // سهم تخفیف بازارک به نسبت مبلغ برگشتی (گرد به ۰٫۰۱)
                    $platformDiscount = Money::of(bcdiv(
                        bcadd(bcmul($remainingDiscount, $gross, 6), bcdiv($remainingGross, '200', 6), 6), $remainingGross, 2));
                    $commission = match (true) {
                        $sale->seller_id === null => '0.00',
                        // مبلغ ثابت: به نسبت مبلغ برگشتی از کمیسیون باقی‌مانده
                        ($sale->commission_type ?? 'percent') === 'fixed' => Money::of(bcdiv(
                            bcadd(bcmul($remainingCommission, $gross, 6), bcdiv($remainingGross, '200', 6), 6), $remainingGross, 2)),
                        default => Money::percentOf($gross, $sale->commission_rate),
                    };
                    $sellerAmount = $sale->seller_id ? Money::sub($gross, $commission) : '0.00';
                }

                $entry = CommissionEntry::create([
                    'idempotency_key' => $idempotencyKey,
                    'seller_id' => $sale->seller_id,
                    'kind' => CommissionEntry::KIND_REVERSAL,
                    'source_type' => explode(':', $idempotencyKey)[0],
                    'source_id' => is_numeric(explode(':', $idempotencyKey)[1] ?? null) ? (int) explode(':', $idempotencyKey)[1] : null,
                    'order_id' => $sale->order_id,
                    'seller_order_id' => $sale->seller_order_id,
                    'category_id' => $sale->category_id,
                    'description' => $description,
                    'gross_amount' => '-'.$gross,
                    'platform_discount' => Money::sub(0, $platformDiscount),
                    'commission_type' => $sale->commission_type ?? 'percent',
                    'commission_rate' => $sale->commission_rate,
                    'rate_source' => $sale->rate_source,
                    'commission_amount' => Money::sub(0, $commission),
                    'seller_amount' => Money::sub(0, $sellerAmount),
                    'reverses_id' => $sale->id,
                    'occurred_at' => now(),
                    'created_by' => $actor?->id,
                ]);

                if ($sale->seller_id) {
                    // پیوندها و «قابل تسویه بودن» از ردیف طلب اصلی: اگر آن هنوز منتظر پول پیک است، این هم منتظر است
                    $original = SellerTransaction::query()->where('commission_entry_id', $sale->id)->first();
                    $this->ledger->append($sale->seller()->firstOrFail(), 'reversal', Money::sub(0, $sellerAmount), [
                        'commission_entry_id' => $entry->id,
                        'order_id' => $original?->order_id ?? $sale->order_id,
                        'seller_order_id' => $original?->seller_order_id ?? $sale->seller_order_id,
                        'payment_id' => $original?->payment_id,
                        'eligible_at' => $original === null ? now() : ($original->eligible_at ?? null),
                        'description' => $description,
                        'created_by' => $actor?->id,
                    ]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException $e) {
            return CommissionEntry::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    // ───────────────────────── حساب فروشنده ─────────────────────────

    /** مانده‌ی حساب فروشنده (طلب او از بازارک؛ منفی = بدهکار). */
    public function balance(Seller $seller): string
    {
        return $this->ledger->balance($seller);
    }

    /**
     * پرداخت به فروشنده — همان SellerLedger::settle (تسویه‌ی کامل یا جزئی از «قابل تسویه»).
     *
     * @throws SettlementException
     */
    public function settle(Seller $seller, ?string $amount, string $method, User $actor, ?string $reference = null, ?string $notes = null, ?DateTimeInterface $paidAt = null, ?DateTimeInterface $periodFrom = null, ?DateTimeInterface $periodTo = null): SellerSettlement
    {
        return $this->ledger->settle($seller, $amount, $method, $actor, $reference, $notes, $paidAt, $periodFrom, $periodTo);
    }

    /** اصلاح دستی حساب (مثبت = به طلب فروشنده اضافه شود). دلیل لازم است. */
    public function adjust(Seller $seller, string $amount, string $reason, User $actor): SellerTransaction
    {
        $amount = Money::of($amount);
        if (Money::isZero($amount) || trim($reason) === '') {
            throw new InvalidArgumentException('Adjustment needs a non-zero amount and a reason.');
        }

        if (! in_array($actor->role, [UserRole::Owner, UserRole::Manager], true)) {
            throw new SettlementException('not_allowed');
        }

        $transaction = $this->ledger->append($seller, 'adjustment', $amount, [
            'description' => trim($reason),
            'created_by' => $actor->id,
        ]);
        $this->logger->log('seller.adjusted', $seller, ['amount' => ['old' => null, 'new' => $amount], 'reason' => ['old' => null, 'new' => trim($reason)]], $actor);

        return $transaction;
    }

    /**
     * جمع‌ها برای گزارش (فروش فروشندگان و فروش خود بازارک جدا).
     *
     * @return array{marketplace_sales: string, commission: string, seller_earnings: string, own_store_sales: string, settled: string, outstanding: string}
     */
    public function totals(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $entries = CommissionEntry::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<', $to));

        $marketplace = (clone $entries)->whereNotNull('seller_id')
            ->selectRaw('COALESCE(SUM(gross_amount),0) g, COALESCE(SUM(commission_amount),0) c, COALESCE(SUM(seller_amount),0) s')->first();
        $own = (clone $entries)->whereNull('seller_id')->sum('gross_amount');

        // مانده‌ی همه‌ی فروشندگان = آخرین balance_after هر فروشنده
        $latestIds = SellerTransaction::query()->selectRaw('MAX(id)')->groupBy('seller_id');
        $outstanding = SellerTransaction::query()->whereIn('id', $latestIds)->sum('balance_after');

        return [
            'marketplace_sales' => Money::of((string) $marketplace->g),
            'commission' => Money::of((string) $marketplace->c),
            'seller_earnings' => Money::of((string) $marketplace->s),
            'own_store_sales' => Money::of((string) $own),
            'settled' => Money::of((string) SellerSettlement::query()
                ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('paid_at', '<', $to))
                ->sum('amount')),
            'outstanding' => Money::of((string) $outstanding),
        ];
    }
}
