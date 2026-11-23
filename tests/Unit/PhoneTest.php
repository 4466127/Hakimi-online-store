<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Phone;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    public function test_all_common_ways_of_writing_a_number_become_one_form(): void
    {
        $inputs = [
            '0700123456',
            '0700 123 456',
            '0700-123-456',
            '۰۷۰۰۱۲۳۴۵۶',
            '+93700123456',
            '+93 700 123 456',
            '+93 (0) 700 123 456',
            '0093700123456',
            '93700123456',
            '700123456',
        ];

        foreach ($inputs as $input) {
            $this->assertSame('0700123456', Phone::normalize($input), "ورودی: {$input}");
        }
    }

    public function test_wrong_numbers_are_rejected(): void
    {
        foreach ([null, '', 'abc', '12345', '0600123456', '07001234567', '070012345', '+989121234567', '0044700123456'] as $input) {
            $this->assertNull(Phone::normalize($input), 'ورودی: '.var_export($input, true));
            $this->assertFalse(Phone::isValid($input));
        }
    }

    public function test_display_and_links(): void
    {
        // بین دو نشانه‌ی جداسازی چپ‌به‌راست، تا در صفحه‌ی راست‌چین رقم‌ها جابه‌جا نشوند
        $this->assertSame("\u{2066}۰۷۰۰ ۱۲۳ ۴۵۶\u{2069}", Phone::display('0700123456'));
        $this->assertSame('نادرست', Phone::display('نادرست'));
        $this->assertSame('93700123456', Phone::international('0700123456'));
        $this->assertSame('tel:+93700123456', Phone::telLink('0700 123 456'));
        $this->assertSame('https://wa.me/93700123456', Phone::whatsappLink('0700123456'));
        $this->assertSame(
            'https://wa.me/93700123456?text='.rawurlencode('سلام، کفش سایز ۴۲'),
            Phone::whatsappLink('0700123456', 'سلام، کفش سایز ۴۲'),
        );
        $this->assertNull(Phone::whatsappLink('123'));
    }
}
