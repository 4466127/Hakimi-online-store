<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ارقام فارسی و جداکننده‌ی هزارگان.
 * - نمایش: ۱۲٬۳۴۵٫۵
 * - ورود: هم ارقام فارسی و هم انگلیسی (و عربی) قبول می‌شود.
 *
 * الگو از کلاس Num سیستم دکان گرفته شده و با لاراول و bcmath تطبیق داده شده است.
 */
final class Num
{
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const EN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** جداکننده‌ی هزارگان و اعشار فارسی */
    public const THOUSANDS = '٬';

    public const DECIMAL = '٫';

    /** علامت منفی برای نمایش */
    public const MINUS = '−';

    public static function toFa(string|int $s): string
    {
        return str_replace(self::EN, self::FA, (string) $s);
    }

    /** ارقام فارسی و عربی ← انگلیسی (فقط ارقام). */
    public static function toEn(string $s): string
    {
        return str_replace(self::AR, self::EN, str_replace(self::FA, self::EN, $s));
    }

    /**
     * فهمیدن عددی که کاربر نوشته. خروجی شکل استاندارد است ("12345.5") یا اگر عدد نبود null.
     * قبول می‌کند: ۱۲٬۳۴۵٫۵ ، 12,345.5 ، ۱ ۰۰۰ ، −۲۵ ، ٫۵
     */
    public static function parse(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = self::toEn(trim($input));
        // جداکننده‌های هزارگان و فاصله‌ها حذف می‌شوند
        $s = str_replace([self::THOUSANDS, ',', '،', ' ', "\u{00A0}", "\u{202F}", "\u{066C}"], '', $s);
        // اعشار فارسی و عربی ← نقطه
        $s = str_replace([self::DECIMAL, "\u{066B}"], '.', $s);
        // انواع علامت منفی
        $s = str_replace([self::MINUS, '–', '‐'], '-', $s);
        if ($s === '' || ! preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $s, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            return null;
        }
        $int = ltrim($m[2], '0');
        $int = $int === '' ? '0' : $int;
        $frac = rtrim($m[3] ?? '', '0');
        $out = $frac === '' ? $int : $int.'.'.$frac;

        return ($m[1] === '-' && $out !== '0') ? '-'.$out : $out;
    }

    /**
     * نمایش عدد با ارقام فارسی و جداکننده‌ی هزارگان.
     * $decimals: حد اکثر رقم اعشار (با گرد کردن). $trim: صفرهای بی‌فایده‌ی آخر حذف شوند.
     */
    public static function format(string|int|float $n, int $decimals = 0, bool $trim = true): string
    {
        $fixed = self::round($n, $decimals);
        $negative = str_starts_with($fixed, '-');
        if ($negative) {
            $fixed = substr($fixed, 1);
        }
        [$int, $frac] = array_pad(explode('.', $fixed, 2), 2, '');
        $int = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', self::THOUSANDS, $int);
        if ($trim) {
            $frac = rtrim($frac, '0');
        }
        $out = $frac === '' ? $int : $int.self::DECIMAL.$frac;

        return ($negative ? self::MINUS : '').self::toFa($out);
    }

    /**
     * گرد کردن دقیق (نیم به بالا) بی استفاده از float. خروجی با ارقام انگلیسی: "1234.50".
     */
    public static function round(string|int|float $n, int $decimals = 0): string
    {
        $s = self::toNumericString($n);
        $negative = str_starts_with($s, '-');
        $abs = ltrim($s, '-');
        $half = '0.'.str_repeat('0', $decimals).'5';
        $rounded = bcadd($abs, $half, $decimals);
        $isZero = bccomp($rounded, '0', $decimals) === 0;

        return ($negative && ! $isZero ? '-' : '').$rounded;
    }

    /**
     * عدد کامل به حروف دری: ۱۲۵۰۰ ← «دوازده هزار و پنجصد». صدها به شکل افغانستان (دوصد، سه‌صد، پنجصد).
     * عدد اعشاری یا بزرگ‌تر از ۹۹۹ میلیارد ← متن خالی.
     * واژه‌ها از فایل ترجمه (num.words_*) می‌آید.
     */
    public static function words(string|int|float $n): string
    {
        $fixed = self::round($n, 4);
        $negative = str_starts_with($fixed, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($fixed, '-'), 2), 2, '');
        if (rtrim($frac, '0') !== '' || strlen($int) > 12) {
            return '';
        }
        $ones = explode('|', (string) __('num.words_ones'));
        if ((int) $int === 0) {
            return $ones[0];
        }
        $tens = explode('|', (string) __('num.words_tens'));
        $hundreds = explode('|', (string) __('num.words_hundreds'));
        $scales = explode('|', (string) __('num.words_scales'));
        $and = (string) __('num.words_and');

        $groups = [];
        for ($s = $int; $s !== ''; $s = substr($s, 0, -3)) {
            $groups[] = (int) substr($s, -3);
        }
        $out = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $g = $groups[$i];
            if ($g === 0) {
                continue;
            }
            $parts = [];
            if ($g >= 100) {
                $parts[] = $hundreds[intdiv($g, 100)];
            }
            $r = $g % 100;
            if ($r > 0 && $r < 20) {
                $parts[] = $ones[$r];
            } elseif ($r >= 20) {
                $parts[] = $tens[intdiv($r, 10)];
                if ($r % 10 > 0) {
                    $parts[] = $ones[$r % 10];
                }
            }
            $out[] = implode($and, $parts).($scales[$i] !== '' ? ' '.$scales[$i] : '');
        }

        return ($negative ? __('num.words_minus').' ' : '').implode($and, $out);
    }

    /** هر ورودی عددی ← رشته‌ی عددی مناسب bcmath. ورودی نادرست صفر حساب می‌شود. */
    private static function toNumericString(string|int|float $n): string
    {
        if (is_int($n)) {
            return (string) $n;
        }
        if (is_float($n)) {
            return number_format($n, 6, '.', '');
        }

        return self::parse($n) ?? '0';
    }
}
