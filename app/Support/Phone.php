<?php

declare(strict_types=1);

namespace App\Support;

/**
 * شماره‌ی موبایل افغانستان.
 *
 * شکل استاندارد در دیتابیس: «07XXXXXXXX» (ده رقم، ارقام انگلیسی).
 * ورودی‌های قبول‌شده: ۰۷۰۰ ۱۲۳ ۴۵۶ ، 0700-123-456 ، +93700123456 ، 0093700123456 ، 93700123456 ، 700123456
 */
final class Phone
{
    /** U+2066 LEFT-TO-RIGHT ISOLATE */
    public const LTR_ISOLATE = "\u{2066}";

    /** U+2069 POP DIRECTIONAL ISOLATE */
    public const POP_ISOLATE = "\u{2069}";

    /** شماره‌ی نرمال‌شده، یا null اگر شماره‌ی موبایل افغانستان نبود. */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = Num::toEn(trim($input));
        $hasPlus = str_starts_with($s, '+');
        $digits = (string) preg_replace('/\D+/', '', $s);
        if ($digits === '') {
            return null;
        }

        if ($hasPlus || str_starts_with($digits, '00')) {
            // شکل بین‌المللی: باید کد افغانستان (۹۳) باشد
            $digits = $hasPlus ? $digits : substr($digits, 2);
            if (! str_starts_with($digits, '93')) {
                return null;
            }
            // «+93 (0)700…» هم قبول است
            $digits = '0'.ltrim(substr($digits, 2), '0');
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '937')) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^07\d{8}$/', $digits) ? $digits : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** شکل بین‌المللی بی علامت جمع، برای لینک واتساپ: 93700123456 */
    public static function international(?string $input): ?string
    {
        $n = self::normalize($input);

        return $n === null ? null : '93'.substr($n, 1);
    }

    /**
     * نمایش خوانا با ارقام فارسی: ۰۷۰۰ ۱۲۳ ۴۵۶. اگر شماره نادرست بود همان ورودی برمی‌گردد.
     *
     * شماره میان دو نشانه‌ی نامرئی یونیکد «جداسازی چپ‌به‌راست» (LRI … PDI) پیچیده می‌شود؛
     * بی آن، در صفحه‌ی راست‌چین گروه‌های رقم جابه‌جا دیده می‌شوند (۴۵۶ ۱۲۳ ۰۷۰۰).
     */
    public static function display(?string $input): string
    {
        $n = self::normalize($input);
        if ($n === null) {
            return (string) $input;
        }

        return self::LTR_ISOLATE.Num::toFa(substr($n, 0, 4).' '.substr($n, 4, 3).' '.substr($n, 7)).self::POP_ISOLATE;
    }

    /** لینک تماس: tel:+93700123456 */
    public static function telLink(?string $input): ?string
    {
        $i = self::international($input);

        return $i === null ? null : 'tel:+'.$i;
    }

    /** لینک واتساپ با متن اختیاری: https://wa.me/93700123456?text=... */
    public static function whatsappLink(?string $input, ?string $text = null): ?string
    {
        $i = self::international($input);
        if ($i === null) {
            return null;
        }

        return 'https://wa.me/'.$i.($text !== null && $text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
