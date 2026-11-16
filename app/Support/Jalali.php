<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * تاریخ هجری شمسی با ماه‌های افغانستان (حمل، ثور، ...).
 *
 * در دیتابیس همیشه زمان استاندارد (UTC) ذخیره می‌شود؛ این کلاس فقط برای نمایش و ورود به شمسی است.
 * الگوریتم: روش حسابی ۳۳ساله (همان الگوریتم مشهور jdf)، که برای سال‌های ۱۳۴۳ تا ۱۴۷۳ با تقویم رسمی
 * یکی است. الگو از کلاس Jalali سیستم دکان گرفته شده و با لاراول تطبیق داده شده است.
 */
final class Jalali
{
    /** @return array{0:int,1:int,2:int} [سال، ماه، روز] شمسی */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gDaysBeforeMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $gDaysBeforeMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} [سال، ماه، روز] میلادی */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
            + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $days--;
            $gy += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0);
        $monthDays = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 12 && $gd > $monthDays[$gm]) {
            $gd -= $monthDays[$gm];
            $gm++;
        }

        return [$gy, $gm + 1, $gd];
    }

    /** سال کبیسه: حوت ۳۰ روز دارد. */
    public static function isLeap(int $jy): bool
    {
        [$gy, $gm, $gd] = self::toGregorian($jy, 12, 30);

        return self::fromGregorian($gy, $gm, $gd) === [$jy, 12, 30];
    }

    public static function daysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isLeap($jy) ? 30 : 29;
    }

    public static function isValid(int $jy, int $jm, int $jd): bool
    {
        return $jy >= 1 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= self::daysInMonth($jy, $jm);
    }

    /** تاریخ میلادی (2026-11-09) ← شمسی کوتاه با ارقام انگلیسی (1405/08/18). */
    public static function fromDateString(string $ymd): string
    {
        [$jy, $jm, $jd] = self::partsFromDateString($ymd);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /**
     * ورودی کاربر به شمسی ← تاریخ میلادی (Y-m-d). اگر درست نبود، null.
     * قبول می‌کند: ۱۴۰۵/۸/۱۸ ، 1405-08-18 ، 1405.08.18
     */
    public static function toDateString(string $input): ?string
    {
        $s = Num::toEn(trim($input));
        if (! preg_match('#^(\d{4})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{1,2})$#', $s, $m)) {
            return null;
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if (! self::isValid($jy, $jm, $jd)) {
            return null;
        }
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /** نمایش کوتاه با ارقام فارسی: ۱۴۰۵/۰۸/۱۸ */
    public static function formatShort(string $ymd): string
    {
        return Num::toFa(self::fromDateString($ymd));
    }

    /** نمایش کامل: ۱۸ عقرب ۱۴۰۵ */
    public static function formatLong(string $ymd): string
    {
        [$jy, $jm, $jd] = self::partsFromDateString($ymd);
        $months = (array) __('date.months');
        $month = $months[$jm - 1] ?? (string) $jm;

        return Num::toFa($jd).' '.$month.' '.Num::toFa($jy);
    }

    /** نام روز هفته: دوشنبه */
    public static function weekday(string $ymd): string
    {
        $w = (int) (new DateTimeImmutable($ymd))->format('w'); // ۰ = یکشنبه
        $names = (array) __('date.weekdays');

        return (string) ($names[$w] ?? '');
    }

    /** تاریخ امروز به میلادی، به وقت کابل. */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', self::timezone()))->format('Y-m-d');
    }

    /** امروز با روز هفته: دوشنبه، ۱۸ عقرب ۱۴۰۵ */
    public static function todayLong(): string
    {
        $today = self::today();

        return self::weekday($today).'، '.self::formatLong($today);
    }

    /**
     * زمان ذخیره‌شده (UTC) ← نمایش به وقت کابل: «۱۸ عقرب ۱۴۰۵، ۱۴:۳۰».
     * هم شیء تاریخ (Carbon) و هم متن «2026-11-09 10:00:00» را قبول می‌کند.
     */
    public static function dateTime(DateTimeInterface|string|null $utc): string
    {
        $local = self::toLocal($utc);
        if ($local === null) {
            return '';
        }

        return self::formatLong($local->format('Y-m-d')).'، '.Num::toFa($local->format('H:i'));
    }

    /** فقط تاریخ به وقت کابل: «۱۸ عقرب ۱۴۰۵» */
    public static function date(DateTimeInterface|string|null $utc): string
    {
        $local = self::toLocal($utc);

        return $local === null ? '' : self::formatLong($local->format('Y-m-d'));
    }

    /** فقط ساعت به وقت کابل: «۱۴:۳۰» */
    public static function time(DateTimeInterface|string|null $utc): string
    {
        $local = self::toLocal($utc);

        return $local === null ? '' : Num::toFa($local->format('H:i'));
    }

    /** سال شمسی یک تاریخ میلادی. */
    public static function year(string $ymd): int
    {
        return self::partsFromDateString($ymd)[0];
    }

    /** منطقه‌ی زمانی نمایش (پیش‌فرض کابل؛ از config/store.php). */
    private static function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('store.timezone', 'Asia/Kabul'));
    }

    private static function toLocal(DateTimeInterface|string|null $utc): ?DateTimeImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        if (is_string($utc)) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
            if ($dt === false) {
                return null;
            }
        } else {
            $dt = DateTimeImmutable::createFromInterface($utc);
        }

        return $dt->setTimezone(self::timezone());
    }

    /** @return array{0:int,1:int,2:int} */
    private static function partsFromDateString(string $ymd): array
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            throw new InvalidArgumentException('Invalid date: '.$ymd);
        }

        return self::fromGregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    }
}
