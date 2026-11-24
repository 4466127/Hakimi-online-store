<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Enums\UserRole;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementItem;
use App\Models\SellerTransaction;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * دفتر حساب فروشنده و تسویه (مرحله‌ی ۴).
 *
 * - هر رویداد یک ردیف تازه است (طلب، برگشت، اصلاح، تسویه)؛ مبلغ و مانده هیچ‌وقت ویرایش نمی‌شود.
 * - «مانده» = آخرین balance_after (طلب فروشنده از بازارک)؛ «باز» = چیزی که هنوز تسویه نشده (open_amount).
 * - «قابل تسویه» = ردیف باز که eligible_at آن رسیده. طلبِ پولی که هنوز دست پیک است eligible_at ندارد
 *   تا پیک پول را به صندوق بدهد (تصمیم ۱ صاحب پروژه، ۲ قوس ۱۴۰۵)؛ بعد از آن + «روزهای انتظار» تنظیمات.
 * - تسویه فقط وقتی ثبت می‌شود که پول واقعاً داده شده (روش، مرجع، شخص و تاریخ)؛ با قفل ردیف فروشنده
 *   و ردیف‌های دفتر، تا یک طلب هرگز دو بار پرداخت نشود (حتی با دو درخواست همزمان).
 * - همه‌ی حساب‌ها با bcmath (Money)، هرگز float.
 */
class SellerLedger
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $logger,
    ) {}

    // ───────────────────────── ثبت ردیف ─────────────────────────

    /**
     * یک ردیف به دفتر اضافه می‌کند (با قفل ردیف فروشنده تا مانده درست بماند).
     * open_amount پیش‌فرض = خود مبلغ (برای تسویه: ۰). eligible_at پیش‌فرض: همین حالا (برای اصلاح دستی).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(Seller $seller, string $type, string $amount, array $attributes = [], bool $lock = true): SellerTransaction
    {
        return DB::transaction(function () use ($seller, $type, $amount, $attributes, $lock): SellerTransaction {
            if ($lock) {
                $this->lockSeller($seller);
            }
            $amount = Money::of($amount);
            $balance = Money::add($this->balance($seller), $amount);

            $tx = SellerTransaction::create([
                'seller_id' => $seller->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balance,
                'open_amount' => $type === 'payout' ? '0.00' : $amount,
                'eligible_at' => array_key_exists('eligible_at', $attributes) ? $attributes['eligible_at'] : now(),
                ...collect($attributes)->except('eligible_at')->all(),
            ]);

            // شماره‌ی یکتا از شناسه (ST-100001)؛ مستقیم در دیتابیس، چون مدل ویرایش را رد می‌کند
            $reference = SellerTransaction::referenceFor($tx->id);
            SellerTransaction::query()->whereKey($tx->id)->toBase()->update(['reference' => $reference]);
            $tx->setRawAttributes([...$tx->getAttributes(), 'reference' => $reference], true);

            return $tx;
        });
    }

    /** مانده‌ی حساب فروشنده (طلب او از بازارک؛ منفی = بدهکار). */
    public function balance(Seller $seller): string
    {
        $last = SellerTransaction::query()->where('seller_id', $seller->id)->orderByDesc('id')->value('balance_after');

        return Money::of($last ?? 0);
    }

    public function lockSeller(Seller $seller): void
    {
        Seller::query()->whereKey($seller->id)->lockForUpdate()->first();
    }

    // ───────────────────────── قابل تسویه شدن ─────────────────────────

    /** روزهای انتظار پیش از قابل تسویه شدن (تنظیمات؛ پیش‌فرض ۰). */
    public function holdDays(): int
    {
        return max(0, (int) $this->settings->get('marketplace.settlement_hold_days', 0));
    }

    /** لحظه‌ای که طلب تازه قابل تسویه می‌شود (اگر پول به صندوق رسیده باشد). */
    public function eligibleFrom(?DateTimeInterface $at = null): Carbon
    {
        return Carbon::instance($at ?? now())->addDays($this->holdDays());
    }

    /**
     * پول این پرداخت‌ها به صندوق رسید (پیک تحویل داد): طلب‌های منتظرِ وابسته قابل تسویه می‌شوند.
     *
     * @param  list<int>  $paymentIds
     */
    public function releaseForPayments(array $paymentIds, ?DateTimeInterface $at = null): int
    {
        if ($paymentIds === []) {
            return 0;
        }

        // فقط دو ستون «پیگیری تسویه» عوض می‌شود؛ مبلغ‌ها دست نمی‌خورد
        return SellerTransaction::query()
            ->whereIn('payment_id', $paymentIds)
            ->whereNull('eligible_at')
            ->where('open_amount', '!=', 0)
            ->toBase()
            ->update(['eligible_at' => $this->eligibleFrom($at)]);
    }

    // ───────────────────────── گزارش هر فروشنده ─────────────────────────

    /**
     * خلاصه‌ی حساب یک فروشنده.
     *
     * @return array{earned: string, reversed: string, adjusted: string, pending: string, eligible: string, settled: string, balance: string}
     */
    public function summary(Seller $seller): array
    {
        $sum = fn ($q, string $column = 'amount'): string => Money::of((string) $q->sum($column));
        $base = fn () => SellerTransaction::query()->where('seller_id', $seller->id);

        return [
            'earned' => $sum($base()->where('type', 'earning')),                       // طلب از فروش‌ها
            'reversed' => $sum($base()->where('type', 'reversal')),                    // برگشت‌ها (منفی)
            'adjusted' => $sum($base()->where('type', 'adjustment')),                  // اصلاح دستی (±)
            'pending' => $sum($base()->pending(), 'open_amount'),                      // هنوز قابل تسویه نیست
            'eligible' => $sum($base()->eligible(), 'open_amount'),                    // قابل تسویه
            'settled' => Money::of((string) SellerSettlement::query()->where('seller_id', $seller->id)->sum('amount')),
            'balance' => $this->balance($seller),                                      // کل طلب باقی‌مانده
        ];
    }

    // ───────────────────────── تسویه ─────────────────────────

    /**
     * پرداخت واقعی به فروشنده (کامل یا جزئی). فقط بعد از اینکه پول واقعاً داده شد صدا زده شود.
     *
     * ترتیب برداشت: اول ردیف‌های منفیِ قابل تسویه (برگشت/کسر) کامل بسته می‌شوند، بعد ردیف‌های طلب
     * از قدیمی به تازه، تا مبلغ پر شود. مبلغ نه بیشتر از «قابل تسویه» و نه بیشتر از مانده.
     *
     * @param  string|null  $amount  null = همه‌ی قابل تسویه
     *
     * @throws SettlementException
     */
    public function settle(Seller $seller, ?string $amount, string $method, User $actor, ?string $reference = null, ?string $notes = null, ?DateTimeInterface $paidAt = null, ?DateTimeInterface $periodFrom = null, ?DateTimeInterface $periodTo = null): SellerSettlement
    {
        if (! in_array($actor->role, [UserRole::Owner, UserRole::Manager], true)) {
            throw new SettlementException('not_allowed');
        }
        if (! in_array($method, SellerSettlement::METHODS, true)) {
            throw new SettlementException('unknown_method');
        }
        if ($method !== 'cash' && trim((string) $reference) === '') {
            throw new SettlementException('reference_required');           // انتقال بی شماره‌ی مرجع پذیرفته نیست
        }

        return DB::transaction(function () use ($seller, $amount, $method, $actor, $reference, $notes, $paidAt, $periodFrom, $periodTo): SellerSettlement {
            $this->lockSeller($seller);

            $rows = SellerTransaction::query()
                ->where('seller_id', $seller->id)
                ->eligible()
                ->orderByRaw('open_amount < 0 DESC')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $eligible = $rows->reduce(fn (string $c, SellerTransaction $t) => Money::add($c, (string) $t->open_amount), '0.00');
            if (Money::compare($eligible, 0) <= 0) {
                // اگر طلبی فقط منتظر «روزهای انتظار پیش از تسویه» است، تاریخ دقیقش را به مدیر بگو
                $next = SellerTransaction::query()
                    ->where('seller_id', $seller->id)
                    ->pending()
                    ->where('open_amount', '>', 0)
                    ->whereNotNull('eligible_at')
                    ->orderBy('eligible_at')
                    ->first();
                if ($next !== null) {
                    throw new SettlementException('not_yet_eligible', [
                        'date' => Jalali::dateTime($next->eligible_at),
                        'days' => Num::toFa((string) $this->holdDays()),
                    ]);
                }
                throw new SettlementException('nothing_eligible');
            }

            $amount = $amount === null || trim($amount) === '' ? $eligible : Money::of($amount);
            if (Money::compare($amount, 0) <= 0) {
                throw new SettlementException('amount_positive');
            }
            if (Money::compare($amount, $eligible) > 0) {
                throw new SettlementException('more_than_eligible');
            }
            if (Money::compare($amount, $this->balance($seller)) > 0) {
                throw new SettlementException('more_than_balance');
            }

            $settlement = SellerSettlement::create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference === null || trim($reference) === '' ? null : trim($reference),
                'notes' => $notes,
                'paid_at' => $paidAt ?? now(),
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'created_by' => $actor->id,
            ]);
            $number = SellerSettlement::numberFor($settlement->id);
            SellerSettlement::query()->whereKey($settlement->id)->toBase()->update(['number' => $number]);
            $settlement->setRawAttributes([...$settlement->getAttributes(), 'number' => $number], true);

            // ظرفیت = مبلغ پرداخت + هر چه ردیف‌های منفی از طلب کم کرده‌اند
            $capacity = $amount;
            foreach ($rows as $row) {
                $open = (string) $row->open_amount;
                if (Money::compare($open, 0) < 0) {
                    $take = $open;                                   // ردیف منفی کامل بسته می‌شود
                    $capacity = Money::sub($capacity, $open);
                } else {
                    if (Money::compare($capacity, 0) <= 0) {
                        break;
                    }
                    $take = Money::compare($open, $capacity) > 0 ? $capacity : $open;
                    $capacity = Money::sub($capacity, $take);
                }

                SellerSettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'seller_transaction_id' => $row->id,
                    'amount' => $take,
                ]);
                $row->forceFill(['open_amount' => Money::sub($open, $take)])->save();
            }

            $this->append($seller, 'payout', Money::sub(0, $amount), [
                'settlement_id' => $settlement->id,
                'description' => $reference,
                'created_by' => $actor->id,
            ], lock: false);

            $this->logger->log('seller.settled', $seller, [
                'settlement' => ['old' => null, 'new' => $number],
                'amount' => ['old' => null, 'new' => $amount],
                'method' => ['old' => null, 'new' => $method],
            ], $actor);

            return $settlement;
        });
    }
}
