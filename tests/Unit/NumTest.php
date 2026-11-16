<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Num;
use Tests\TestCase;

class NumTest extends TestCase
{
    public function test_digits_are_converted_between_persian_and_english(): void
    {
        $this->assertSame('۱۲۳۴۵۶۷۸۹۰', Num::toFa('1234567890'));
        $this->assertSame('۴۲', Num::toFa(42));
        $this->assertSame('0700123456', Num::toEn('۰۷۰۰۱۲۳۴۵۶'));
        // ارقام عربی هم قبول است
        $this->assertSame('456', Num::toEn('٤٥٦'));
    }

    public function test_parse_understands_what_people_type(): void
    {
        $this->assertSame('12345.5', Num::parse('۱۲٬۳۴۵٫۵'));
        $this->assertSame('12345.5', Num::parse('12,345.5'));
        $this->assertSame('1000', Num::parse('۱ ۰۰۰'));
        $this->assertSame('-25', Num::parse('−۲۵'));
        $this->assertSame('0.5', Num::parse('٫۵'));
        $this->assertSame('7', Num::parse('007.00'));
        $this->assertSame('0', Num::parse('-0'));
    }

    public function test_parse_rejects_things_that_are_not_numbers(): void
    {
        $this->assertNull(Num::parse(null));
        $this->assertNull(Num::parse(''));
        $this->assertNull(Num::parse('abc'));
        $this->assertNull(Num::parse('12.3.4'));
        $this->assertNull(Num::parse('.'));
    }

    public function test_format_adds_separators_and_persian_digits(): void
    {
        $this->assertSame('۲٬۵۰۰', Num::format(2500));
        $this->assertSame('۱٬۲۳۴٬۵۶۷', Num::format('1234567.00'));
        $this->assertSame('۱۲٫۵', Num::format('12.50', 2));
        $this->assertSame('۱۲٫۵۰', Num::format('12.50', 2, trim: false));
        $this->assertSame('−۳۰۰', Num::format(-300));
        $this->assertSame('۰', Num::format(0));
    }

    public function test_round_is_exact_half_up(): void
    {
        $this->assertSame('3', Num::round('2.5'));
        $this->assertSame('2', Num::round('2.49'));
        $this->assertSame('1.01', Num::round('1.005', 2));
        $this->assertSame('-3', Num::round('-2.5'));
        $this->assertSame('0', Num::round('-0.2'));
        $this->assertSame('1250.00', Num::round(1250, 2));
    }

    public function test_words_use_afghan_hundreds(): void
    {
        $this->assertSame('صفر', Num::words(0));
        $this->assertSame('دوازده هزار و پنجصد', Num::words(12500));
        $this->assertSame('دوصد و پنجاه', Num::words(250));
        $this->assertSame('یک میلیون و یک', Num::words(1000001));
        $this->assertSame('منفی سه‌صد', Num::words(-300));
        // عدد اعشاری به حروف نوشته نمی‌شود
        $this->assertSame('', Num::words('12.5'));
    }
}
