<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Persian;
use Tests\TestCase;

class PersianTest extends TestCase
{
    public function test_arabic_and_persian_letters_become_the_same(): void
    {
        // ك و ي عربی ← ک و ی فارسی
        $this->assertSame(Persian::normalize('کفش چرمی'), Persian::normalize('كفش چرمي'));
        $this->assertSame('کفش چرمی', Persian::normalize('كفش چرمي'));
    }

    public function test_diacritics_tatweel_and_half_space_are_removed(): void
    {
        $this->assertSame('محمد', Persian::normalize('مُحَمَّد'));
        $this->assertSame('کتاب', Persian::normalize('کـتـاب'));
        // نیم‌فاصله ← فاصله، تا «می‌خواهم» و «می خواهم» یکی باشد
        $this->assertSame('می خواهم', Persian::normalize("می\u{200C}خواهم"));
    }

    public function test_digits_case_and_punctuation(): void
    {
        $this->assertSame('iphone 15 pro 256 gb', Persian::normalize('iPhone ۱۵ Pro، 256-GB!'));
        $this->assertSame('', Persian::normalize(null));
        $this->assertSame('', Persian::normalize('  ،؛!  '));
    }

    public function test_slug_keeps_persian_letters(): void
    {
        $this->assertSame('کفش-چرمی-مردانه', Persian::slug(' کفش  چرمی مردانه '));
        $this->assertSame('about-us', Persian::slug('About Us'));
        $this->assertSame('کفش-42', Persian::slug('كفش ۴۲'));
        $this->assertSame('کفش', Persian::slug('کفش چرمی مردانه', 4));
    }
}
