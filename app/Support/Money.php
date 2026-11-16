<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Currency;

/**
 * حساب و نمایش پول.
 *
 * همه‌ی مبلغ‌ها به افغانی و به شکل متن عددی با دو رقم اعشار ("1250.00") نگهداری می‌شود
 * و با bcmath حساب می‌شود؛ هیچ‌وقت float، تا یک افغانی هم گم نشود.
 */
final class Money
{
    public const SCALE = 2;

    /** هر عدد ← مبلغ استاندارد با دو رقم اعشار، گردشده. */
    public static function of(string|int|float|null $amount): string
    {
        return Num::round($amount ?? 0, self::SCALE);
    }

    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    /** قیمت × تعداد (تعداد می‌تواند اعشاری باشد)، گردشده به دو رقم. */
    public static function multiply(string|int|float|null $amount, string|int|float $qty): string
    {
        return Num::round(bcmul(self::of($amount), Num::round($qty, 3), 6), self::SCALE);
    }

    /** چند فیصد یک مبلغ: percentOf(2000, 15) = 300.00 */
    public static function percentOf(string|int|float|null $amount, string|int|float $percent): string
    {
        return Num::round(bcdiv(bcmul(self::of($amount), Num::round($percent, 4), 8), '100', 8), self::SCALE);
    }

    /** مقایسه: -۱ اگر a کوچک‌تر، ۰ برابر، ۱ بزرگ‌تر. */
    public static function compare(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isZero(string|int|float|null $amount): bool
    {
        return self::compare($amount, 0) === 0;
    }

    /**
     * فیصدی تخفیف از قیمت قبلی (عدد کامل، گرد به پایین تا بیشتر از واقعیت نشان ندهد).
     * اگر قیمت قبلی نیست یا بزرگ‌تر از قیمت نیست ← صفر.
     */
    public static function discountPercent(string|int|float|null $price, string|int|float|null $compareAt): int
    {
        if ($compareAt === null || self::compare($compareAt, $price) <= 0 || self::compare($compareAt, 0) <= 0) {
            return 0;
        }
        $saved = self::sub($compareAt, $price);

        return (int) bcdiv(bcmul($saved, '100', 4), self::of($compareAt), 0);
    }

    /**
     * تبدیل مبلغ افغانی به پول دیگر (فقط برای نمایش).
     * $rateToAfn: یک واحد آن پول چند افغانی است.
     */
    public static function convertFromAfn(string|int|float|null $amountAfn, string|int|float $rateToAfn, int $decimals = 2): string
    {
        $rate = Num::round($rateToAfn, 6);
        if (bccomp($rate, '0', 6) <= 0) {
            return Num::round(0, $decimals);
        }

        return Num::round(bcdiv(self::of($amountAfn), $rate, 8), $decimals);
    }

    /**
     * نمایش برای مشتری: «۲٬۵۰۰ افغانی». اگر پول داده نشود، پول پیش‌فرض فروشگاه.
     * مبلغ همیشه به افغانی داده می‌شود؛ اگر پول دیگری بود، با نرخ همان پول تبدیل می‌شود.
     */
    public static function format(string|int|float|null $amountAfn, ?Currency $currency = null): string
    {
        $currency ??= Currency::defaultCurrency();
        if ($currency === null) {
            return Num::format(self::of($amountAfn), 0).' '.__('store.currency_fallback');
        }
        $value = $currency->code === 'AFN'
            ? self::of($amountAfn)
            : self::convertFromAfn($amountAfn, $currency->rate_to_afn, (int) $currency->decimals);

        return Num::format($value, (int) $currency->decimals).' '.$currency->symbol;
    }
}
