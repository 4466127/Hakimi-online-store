<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\CommissionEntry;
use App\Models\DiscountCode;
use App\Models\DiscountCodeRedemption;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * کد تخفیف (مرحله‌ی ۹-۳).
 *
 * قاعده‌های مالی (تصمیم ۱۰ صاحب پروژه، ۱ قوس ۱۴۰۵):
 * - همه‌ی تخفیف کدها از جیب **بازارک** است: پایه‌ی کمیسیون، کمیسیون و سهم فروشنده **کم نمی‌شود**.
 * - فقط روی قیمت اجناس، هرگز روی کرایه یا کارمزد.
 * - مبلغ تخفیف بین خط‌ها **به نسبت مبلغ هر خط** با «بیشترین باقی‌مانده» در واحد ۰٫۰۱ پخش می‌شود
 *   (`order_items.platform_discount`)، تا جمع دقیقاً همان باشد و هیچ پولی گم نشود.
 * - هزینه‌ی واقعی در سند فروش هر خط (`commission_entries.platform_discount`) ثبت می‌شود — همان وقتی که فروش
 *   ثبت می‌شود (تحویل + پول کامل) — و برگشت جنس آن را با سند منفی برمی‌گرداند.
 *
 * قاعده‌های استفاده:
 * - مهمان هم می‌تواند. سقف هر نفر با **موبایل یکسان‌شده‌ی خریدار** شمرده می‌شود. ⚠ موبایل تأیید نمی‌شود (هنوز
 *   پیامک نداریم)، پس کسی که با شماره‌های مختلف سفارش بدهد این سقف را دور می‌زند؛ سقف کل کد همیشه کار می‌کند.
 * - ثبت استفاده زیر قفل ردیف کد (`lockForUpdate`) و داخل transaction ثبت سفارش است؛ دو درخواست همزمان از سقف
 *   بیشتر نمی‌روند. هر سفارش حداکثر یک ردیف استفاده دارد (UNIQUE).
 * - هیچ مبلغی از مرورگر خوانده نمی‌شود؛ تخفیف همیشه در سرور از روی سبد قفل‌شده حساب می‌شود.
 * - سفارش کامل لغو/رد شد ← استفاده آزاد می‌شود (`released`، ردیف می‌ماند). برگشت بخشی از سفارش ← فقط
 *   `returned_amount` (سهم همان اجناس) ثبت می‌شود و استفاده آزاد نمی‌شود.
 * - «فقط خرید اول»: کسی که هیچ سفارشِ «تحویل‌شده و پول کامل‌گرفته» (با همین موبایل یا همین حساب) ندارد.
 *   سفارش لغوشده، ردشده یا پول‌نگرفته مانع نیست.
 */
class DiscountCodeService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * کد را بررسی و مبلغ تخفیف را حساب می‌کند.
     *
     * @param  string  $subtotal  جمع قیمت اجناس (بی کرایه)
     * @param  string|null  $phone  موبایل یکسان‌شده‌ی خریدار؛ خالی = سقف هر نفر و «خرید اول» هنوز بررسی نمی‌شود
     *                              (صفحه‌ی تسویه پیش از نوشتن شماره)؛ ثبت سفارش همیشه با شماره بررسی می‌کند
     * @param  bool  $lock  داخل transaction ثبت سفارش: ردیف کد قفل می‌شود
     * @return array{code: DiscountCode, amount: string}
     *
     * @throws DiscountCodeException
     */
    public function quote(string $rawCode, string $subtotal, ?string $phone = null, ?int $customerId = null, bool $lock = false, ?DateTimeInterface $at = null): array
    {
        $codeText = DiscountCode::normaliseCode($rawCode);
        if ($codeText === '') {
            throw new DiscountCodeException('empty');
        }
        $query = DiscountCode::query()->where('code', $codeText);
        $code = $lock ? $query->lockForUpdate()->first() : $query->first();
        $at ??= now();

        if ($code === null || ! $code->is_active) {
            throw new DiscountCodeException('invalid');
        }
        if ($code->starts_at !== null && $code->starts_at->greaterThan($at)) {
            throw new DiscountCodeException('not_started');
        }
        if ($code->ends_at !== null && $code->ends_at->lessThanOrEqualTo($at)) {
            throw new DiscountCodeException('expired');
        }
        if ($code->max_uses !== null && $code->used_count >= $code->max_uses) {
            throw new DiscountCodeException('exhausted');
        }
        if ($code->min_order_total !== null && Money::compare($subtotal, $code->min_order_total) < 0) {
            throw new DiscountCodeException('min_order', ['amount' => Money::format($code->min_order_total)]);
        }

        if ($phone !== null && $phone !== '') {
            if ($code->max_uses_per_phone !== null) {
                $used = DiscountCodeRedemption::query()
                    ->where('discount_code_id', $code->id)
                    ->where('phone', $phone)
                    ->where('status', DiscountCodeRedemption::ACTIVE)
                    ->count();
                if ($used >= $code->max_uses_per_phone) {
                    throw new DiscountCodeException('per_phone');
                }
            }
            if ($code->first_order_only && $this->hasCompletedOrder($phone, $customerId)) {
                throw new DiscountCodeException('first_order');
            }
        }

        $amount = $this->amountFor($code, $subtotal);
        if (Money::compare($amount, 0) <= 0) {
            throw new DiscountCodeException('invalid');
        }

        return ['code' => $code, 'amount' => $amount];
    }

    /** مبلغ تخفیف: درصدی (با سقف) یا ثابت؛ هرگز بیشتر از جمع اجناس. */
    public function amountFor(DiscountCode $code, string $subtotal): string
    {
        $amount = $code->isPercent()
            ? Money::percentOf($subtotal, (string) $code->value)
            : Money::of((string) $code->value);

        if ($code->isPercent() && $code->max_discount !== null && Money::compare($amount, $code->max_discount) > 0) {
            $amount = Money::of((string) $code->max_discount);
        }
        if (Money::compare($amount, $subtotal) > 0) {
            $amount = Money::of($subtotal);
        }

        return Money::compare($amount, 0) < 0 ? '0.00' : $amount;
    }

    /**
     * پخش مبلغ بین خط‌ها به نسبت مبلغ هر خط («بیشترین باقی‌مانده» در واحد ۰٫۰۱).
     * جمع نتیجه دقیقاً برابر $total است و سهم هیچ خطی از مبلغ خودش بیشتر نمی‌شود.
     *
     * @param  array<int|string, string>  $weights  کلید ← مبلغ خط
     * @return array<int|string, string>
     */
    public function allocate(string $total, array $weights): array
    {
        $result = array_map(fn () => '0.00', $weights);
        $sum = array_reduce($weights, fn (string $c, string $w) => Money::add($c, $w), '0.00');
        if ($weights === [] || Money::compare($total, 0) <= 0 || Money::compare($sum, 0) <= 0) {
            return $result;
        }

        $totalCents = bcmul(Money::of($total), '100', 0);
        $sumCents = bcmul($sum, '100', 0);
        $given = '0';
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exact = bcdiv(bcmul($totalCents, bcmul(Money::of($weight), '100', 0), 0), $sumCents, 8);
            $floor = bcadd($exact, '0', 0);                               // پایین (عدد مثبت)
            $result[$key] = $floor;
            $given = bcadd($given, $floor, 0);
            $remainders[$key] = bcsub($exact, $floor, 8);
        }

        // باقی‌مانده‌ی چند پول به خط‌هایی با بیشترین کسر؛ برابر ← ترتیب خط‌ها
        $left = (int) bcsub($totalCents, $given, 0);
        $order = array_keys($remainders);
        usort($order, fn ($a, $b) => bccomp($remainders[$b], $remainders[$a], 8) ?: (array_search($a, array_keys($weights), true) <=> array_search($b, array_keys($weights), true)));
        foreach ($order as $key) {
            if ($left <= 0) {
                break;
            }
            $result[$key] = bcadd($result[$key], '1', 0);
            $left--;
        }

        return array_map(fn (string $cents) => bcdiv($cents, '100', 2), $result);
    }

    /**
     * ثبت استفاده برای سفارش تازه (داخل transaction ثبت سفارش، بعد از quote با lock).
     */
    public function redeem(Order $order, DiscountCode $code, string $amount, string $phone, ?int $customerId): DiscountCodeRedemption
    {
        $redemption = DiscountCodeRedemption::query()->create([
            'discount_code_id' => $code->id,
            'order_id' => $order->id,
            'customer_id' => $customerId,
            'phone' => $phone,
            'amount' => Money::of($amount),
            'returned_amount' => '0.00',
            'status' => DiscountCodeRedemption::ACTIVE,
        ]);
        DB::table('discount_codes')->where('id', $code->id)->increment('used_count');

        return $redemption;
    }

    /**
     * سفارش کامل لغو/رد شد: استفاده آزاد می‌شود (ردیف تاریخچه می‌ماند). صدا زدن دوباره کاری نمی‌کند.
     */
    public function release(Order $order, string $reason = 'order_cancelled'): bool
    {
        return DB::transaction(function () use ($order, $reason): bool {
            $redemption = DiscountCodeRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($redemption === null || $redemption->status !== DiscountCodeRedemption::ACTIVE) {
                return false;
            }
            $code = DiscountCode::query()->whereKey($redemption->discount_code_id)->lockForUpdate()->first();

            $redemption->forceFill([
                'status' => DiscountCodeRedemption::RELEASED,
                'released_at' => now(),
                'release_reason' => $reason,
            ])->save();
            if ($code !== null && $code->used_count > 0) {
                DB::table('discount_codes')->where('id', $code->id)->decrement('used_count');
            }
            $this->logger->log('discount_code.released', $order, ['code' => $code?->code, 'reason' => $reason]);

            return true;
        });
    }

    /**
     * بخشی از سفارش برگشت خورد: سهم تخفیفِ اجناس برگشتی از روی سندهای برگشت حساب و ثبت می‌شود
     * (از نو جمع زده می‌شود، پس تکرار دو بار حساب نمی‌کند). استفاده‌ی کد آزاد نمی‌شود.
     */
    public function syncReturned(Order $order): void
    {
        $returned = Money::sub(0, (string) CommissionEntry::query()
            ->where('order_id', $order->id)
            ->where('kind', CommissionEntry::KIND_REVERSAL)
            ->sum('platform_discount'));

        DiscountCodeRedemption::query()->where('order_id', $order->id)->update(['returned_amount' => $returned, 'updated_at' => now()]);
    }

    /** آیا این موبایل/حساب سفارشی دارد که تحویل شده و پولش کامل گرفته شده؟ */
    public function hasCompletedOrder(string $phone, ?int $customerId): bool
    {
        return Order::query()
            ->where('payment_status', 'paid')
            ->where('status', Order::STATUS_DELIVERED)
            ->where(function ($query) use ($phone, $customerId): void {
                $query->where('purchaser_phone', $phone);
                if ($customerId !== null) {
                    $query->orWhere('customer_id', $customerId);
                }
            })
            ->exists();
    }
}
