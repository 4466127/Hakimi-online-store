<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Jalali;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_known_dates_convert_both_ways(): void
    {
        // [میلادی، شمسی]
        $pairs = [
            [[2026, 11, 16], [1405, 8, 25]],
            [[2025, 3, 21], [1404, 1, 1]],
            [[2025, 3, 20], [1403, 12, 30]], // آخرین روز سال کبیسه
            [[2024, 3, 20], [1403, 1, 1]],
            [[2000, 1, 1], [1378, 10, 11]],
        ];

        foreach ($pairs as [$gregorian, $jalali]) {
            $this->assertSame($jalali, Jalali::fromGregorian(...$gregorian));
            $this->assertSame($gregorian, Jalali::toGregorian(...$jalali));
        }
    }

    public function test_leap_years_and_month_lengths(): void
    {
        $this->assertTrue(Jalali::isLeap(1403));
        $this->assertFalse(Jalali::isLeap(1404));
        $this->assertSame(31, Jalali::daysInMonth(1405, 1));
        $this->assertSame(30, Jalali::daysInMonth(1405, 8));
        $this->assertSame(30, Jalali::daysInMonth(1403, 12));
        $this->assertSame(29, Jalali::daysInMonth(1404, 12));
    }

    public function test_user_input_is_parsed_with_persian_or_english_digits(): void
    {
        $this->assertSame('2026-11-16', Jalali::toDateString('۱۴۰۵/۸/۲۵'));
        $this->assertSame('2026-11-16', Jalali::toDateString('1405-08-25'));
        $this->assertSame('2026-11-16', Jalali::toDateString(' 1405.08.25 '));
    }

    public function test_invalid_input_returns_null(): void
    {
        $this->assertNull(Jalali::toDateString('1405/13/01'));
        $this->assertNull(Jalali::toDateString('1404/12/30')); // سال عادی: حوت ۲۹ روز
        $this->assertNull(Jalali::toDateString('امروز'));
    }

    public function test_dates_are_shown_with_afghan_month_names(): void
    {
        $this->assertSame('۱۴۰۵/۰۸/۲۵', Jalali::formatShort('2026-11-16'));
        $this->assertSame('۲۵ عقرب ۱۴۰۵', Jalali::formatLong('2026-11-16'));
        $this->assertSame('۱ حمل ۱۴۰۴', Jalali::formatLong('2025-03-21'));
        $this->assertSame('دوشنبه', Jalali::weekday('2026-11-16'));
        $this->assertSame(1405, Jalali::year('2026-11-16'));
    }

    public function test_stored_utc_time_is_shown_in_kabul_time(): void
    {
        // کابل چهار و نیم ساعت از UTC پیش است
        $this->assertSame('۲۵ عقرب ۱۴۰۵، ۱۴:۳۰', Jalali::dateTime('2026-11-16 10:00:00'));
        $this->assertSame('۲۵ عقرب ۱۴۰۵، ۱۴:۳۰', Jalali::dateTime(Carbon::parse('2026-11-16 10:00:00', 'UTC')));
        // نزدیک نیمه‌شب UTC در کابل روز بعد است
        $this->assertSame('۲۶ عقرب ۱۴۰۵', Jalali::date('2026-11-16 21:00:00'));
        $this->assertSame('۰۱:۳۰', Jalali::time('2026-11-16 21:00:00'));
        $this->assertSame('', Jalali::dateTime(null));
    }

    public function test_bad_database_date_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Jalali::formatLong('not-a-date');
    }
}
