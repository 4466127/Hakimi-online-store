<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نرمال‌سازی متن فارسی/دری برای جستجو و ساختن slug.
 *
 * مشتری‌ها با کیبوردهای مختلف می‌نویسند (ي عربی، ك عربی، با یا بی نیم‌فاصله)؛
 * این کلاس همه را یک‌شکل می‌کند تا «كفش» و «کفش» یکی حساب شود.
 */
final class Persian
{
    /** حرف‌هایی که چند شکل دارند ← یک شکل */
    private const LETTERS = [
        'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی', 'ې' => 'ی', 'ۍ' => 'ی',
        'ك' => 'ک', 'ګ' => 'گ',
        'ة' => 'ه', 'ۀ' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و',
    ];

    /**
     * متن برای ستون جستجو و برای عبارت جستجوی مشتری:
     * حرف‌ها یک‌شکل، اعراب و کشیده حذف، نیم‌فاصله ← فاصله، ارقام ← انگلیسی، حروف لاتین کوچک.
     */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $s = Num::toEn($text);
        $s = strtr($s, self::LETTERS);
        // اعراب (فتحه، کسره، تشدید…)، الف کوچک و کشیده
        $s = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
        // نیم‌فاصله و نشانه‌های جهت ← فاصله
        $s = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', ' ', $s);
        $s = mb_strtolower($s, 'UTF-8');
        // نشانه‌گذاری ← فاصله (حرف و رقم هر زبانی می‌ماند)
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * آدرس خوانا: «کفش چرمی مردانه» ← «کفش-چرمی-مردانه». حروف فارسی می‌ماند.
     */
    public static function slug(?string $text, int $maxLength = 150): string
    {
        $s = self::normalize($text);
        $s = str_replace(' ', '-', $s);
        $s = mb_substr($s, 0, $maxLength, 'UTF-8');

        return trim($s, '-');
    }
}
