<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Currency;
use App\Support\Money;
use Tests\TestCase;

/**
 * منطق پول: همه با متن عددی و bcmath، بی float.
 */
class MoneyTest extends TestCase
{
    public function test_amounts_are_normalised_to_two_decimals(): void
    {
        $this->assertSame('1250.00', Money::of(1250));
        $this->assertSame('1250.50', Money::of('1250.5'));
        $this->assertSame('0.00', Money::of(null));
        $this->assertSame('10.01', Money::of('10.005'));
    }

    public function test_add_subtract_and_compare(): void
    {
        // مثال مشهور خطای float: ۰٫۱ + ۰٫۲
        $this->assertSame('0.30', Money::add('0.1', '0.2'));
        $this->assertSame('750.00', Money::sub(1000, 250));
        $this->assertSame(-1, Money::compare(100, '100.01'));
        $this->assertSame(0, Money::compare('100.00', 100));
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertFalse(Money::isZero('0.01'));
    }

    public function test_multiply_price_by_quantity(): void
    {
        $this->assertSame('7500.00', Money::multiply(2500, 3));
        $this->assertSame('1875.00', Money::multiply('1250.00', '1.5'));
        // تعداد تا سه رقم اعشار حساب می‌شود (مثل ستون qty در دیتابیس): ۹۹٫۹۹ × ۰٫۳۳۳ = ۳۳٫۲۹۶۷ ← ۳۳٫۳۰
        $this->assertSame('33.30', Money::multiply('99.99', '0.333'));
    }

    public function test_percent_of_amount(): void
    {
        $this->assertSame('300.00', Money::percentOf(2000, 15));
        $this->assertSame('33.33', Money::percentOf(100, '33.333'));
        $this->assertSame('0.00', Money::percentOf(0, 50));
    }

    public function test_discount_percent_from_old_price(): void
    {
        $this->assertSame(20, Money::discountPercent(2000, 2500));
        // گرد به پایین: ۳۳٫۳٪ ← ۳۳٪ (بیشتر از واقعیت نشان نمی‌دهیم)
        $this->assertSame(33, Money::discountPercent(2000, 3000));
        $this->assertSame(0, Money::discountPercent(2500, 2500));
        $this->assertSame(0, Money::discountPercent(2500, 2000));
        $this->assertSame(0, Money::discountPercent(2500, null));
    }

    public function test_convert_from_afghani_to_another_currency(): void
    {
        $this->assertSame('100.00', Money::convertFromAfn(7000, 70));
        $this->assertSame('35.71', Money::convertFromAfn(2500, 70));
        // نرخ نادرست: صفر، نه خطا
        $this->assertSame('0.00', Money::convertFromAfn(2500, 0));
    }

    public function test_format_shows_persian_digits_and_currency_name(): void
    {
        $afn = new Currency(['code' => 'AFN', 'symbol' => 'افغانی', 'rate_to_afn' => 1, 'decimals' => 0]);
        $usd = new Currency(['code' => 'USD', 'symbol' => 'دالر', 'rate_to_afn' => 70, 'decimals' => 2]);

        $this->assertSame('۲٬۵۰۰ افغانی', Money::format('2500.00', $afn));
        $this->assertSame('۱٬۲۵۱ افغانی', Money::format('1250.50', $afn));
        $this->assertSame('۳۵٫۷۱ دالر', Money::format(2500, $usd));
    }
}
