<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\Seller;
use App\Models\SellerCommissionRate;
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
     * نرخی که برای فروش این فروشنده در این دسته به کار می‌رود.
     * نرخ ویژه‌ی فعال فروشنده: اول نرخ مخصوص همین دسته یا نزدیک‌ترین بالادستش، بعد نرخ «همه‌ی دسته‌ها».
     */
    public function rateFor(?Seller $seller, ?Category $category, ?DateTimeInterface $at = null): AppliedRate
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

        if ($overrides->isNotEmpty()) {
            $chain = $category ? array_reverse([...$category->ancestorIds(), $category->id]) : []; // خود ← ریشه
            foreach ($chain as $categoryId) {
                $match = $overrides->firstWhere('category_id', $categoryId);
                if ($match) {
                    return new AppliedRate(Num::round((string) $match->rate, 2), 'seller', $category?->id, $match->id, $match->ends_at);
                }
            }
            $all = $overrides->firstWhere('category_id', null);
            if ($all) {
                return new AppliedRate(Num::round((string) $all->rate, 2), 'seller', $category?->id, $all->id, $all->ends_at);
            }
        }

        $rate = $this->categoryRate($category);

        return new AppliedRate($rate->rate, $rate->source, $category?->id);
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
        $commission = $line->seller ? Money::percentOf($gross, $rate->rate) : '0.00';
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
                    'rate_source' => $rate->source,
                    'commission_amount' => $commission,
                    'seller_amount' => $sellerAmount,
                    'occurred_at' => $occurredAt,
                    'created_by' => $actor?->id,
                ]);

                if ($line->seller) {
                    $this->appendTransaction($line->seller, 'earning', $sellerAmount, [
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

                $gross = $amount === null ? $remainingGross : Money::of($amount);
                if (Money::compare($gross, 0) <= 0 || Money::compare($gross, $remainingGross) > 0) {
                    throw new InvalidArgumentException('Reversal amount must be positive and not more than what is left.');
                }

                // برگشت کامل باقی‌مانده: دقیقاً همان مبلغ‌ها (بی خطای گرد کردن)
                if (Money::compare($gross, $remainingGross) === 0) {
                    $commission = $remainingCommission;
                    $sellerAmount = $remainingSeller;
                } else {
                    $commission = $sale->seller_id ? Money::percentOf($gross, $sale->commission_rate) : '0.00';
                    $sellerAmount = $sale->seller_id ? Money::sub($gross, $commission) : '0.00';
                }

                $entry = CommissionEntry::create([
                    'idempotency_key' => $idempotencyKey,
                    'seller_id' => $sale->seller_id,
                    'kind' => CommissionEntry::KIND_REVERSAL,
                    'source_type' => explode(':', $idempotencyKey)[0],
                    'source_id' => is_numeric(explode(':', $idempotencyKey)[1] ?? null) ? (int) explode(':', $idempotencyKey)[1] : null,
                    'category_id' => $sale->category_id,
                    'description' => $description,
                    'gross_amount' => '-'.$gross,
                    'commission_rate' => $sale->commission_rate,
                    'rate_source' => $sale->rate_source,
                    'commission_amount' => Money::sub(0, $commission),
                    'seller_amount' => Money::sub(0, $sellerAmount),
                    'reverses_id' => $sale->id,
                    'occurred_at' => now(),
                    'created_by' => $actor?->id,
                ]);

                if ($sale->seller_id) {
                    $this->appendTransaction($sale->seller()->firstOrFail(), 'reversal', Money::sub(0, $sellerAmount), [
                        'commission_entry_id' => $entry->id,
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
        $last = SellerTransaction::query()->where('seller_id', $seller->id)->orderByDesc('id')->value('balance_after');

        return Money::of($last ?? 0);
    }

    /** پرداخت به فروشنده. از مانده بیشتر نمی‌شود. */
    public function settle(Seller $seller, string $amount, string $method, User $actor, ?string $reference = null, ?string $notes = null, ?DateTimeInterface $paidAt = null, ?DateTimeInterface $periodFrom = null, ?DateTimeInterface $periodTo = null): SellerSettlement
    {
        $amount = Money::of($amount);
        if (Money::compare($amount, 0) <= 0) {
            throw new InvalidArgumentException('Settlement amount must be positive.');
        }
        if (! in_array($method, SellerSettlement::METHODS, true)) {
            throw new InvalidArgumentException('Unknown settlement method.');
        }

        return DB::transaction(function () use ($seller, $amount, $method, $actor, $reference, $notes, $paidAt, $periodFrom, $periodTo): SellerSettlement {
            $this->lockSeller($seller);
            if (Money::compare($amount, $this->balance($seller)) > 0) {
                throw new InvalidArgumentException('Settlement is more than the seller balance.');
            }

            $settlement = SellerSettlement::create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'paid_at' => $paidAt ?? now(),
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'created_by' => $actor->id,
            ]);

            $this->appendTransaction($seller, 'payout', Money::sub(0, $amount), [
                'settlement_id' => $settlement->id,
                'description' => $reference,
                'created_by' => $actor->id,
            ], lock: false);

            return $settlement;
        });
    }

    /** اصلاح دستی حساب (مثبت = به طلب فروشنده اضافه شود). دلیل لازم است. */
    public function adjust(Seller $seller, string $amount, string $reason, User $actor): SellerTransaction
    {
        $amount = Money::of($amount);
        if (Money::isZero($amount) || trim($reason) === '') {
            throw new InvalidArgumentException('Adjustment needs a non-zero amount and a reason.');
        }

        $transaction = $this->appendTransaction($seller, 'adjustment', $amount, [
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

    /**
     * یک ردیف به دفتر حساب فروشنده اضافه می‌کند (با قفل ردیف فروشنده تا مانده درست بماند).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function appendTransaction(Seller $seller, string $type, string $amount, array $attributes = [], bool $lock = true): SellerTransaction
    {
        return DB::transaction(function () use ($seller, $type, $amount, $attributes, $lock): SellerTransaction {
            if ($lock) {
                $this->lockSeller($seller);
            }
            $balance = Money::add($this->balance($seller), $amount);

            return SellerTransaction::create([
                'seller_id' => $seller->id,
                'type' => $type,
                'amount' => Money::of($amount),
                'balance_after' => $balance,
                ...$attributes,
            ]);
        });
    }

    private function lockSeller(Seller $seller): void
    {
        Seller::query()->whereKey($seller->id)->lockForUpdate()->first();
    }
}
