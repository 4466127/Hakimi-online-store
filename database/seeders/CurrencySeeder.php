<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * پول‌ها: افغانی (پیش‌فرض) و دالر (غیرفعال؛ جایش برای آینده).
 * چند بار اجرا کردنش بی‌خطر است و نرخی که مدیر عوض کرده دست نمی‌خورد.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->firstOrCreate(['code' => 'AFN'], [
            'name' => ['fa' => 'افغانی'],
            'symbol' => 'افغانی',
            'rate_to_afn' => 1,
            'decimals' => 0,
            'is_default' => true,
            'is_active' => true,
            'position' => 10,
        ]);

        Currency::query()->firstOrCreate(['code' => 'USD'], [
            'name' => ['fa' => 'دالر امریکا'],
            'symbol' => 'دالر',
            'rate_to_afn' => 70, // فقط مقدار اولیه؛ پیش از فعال کردن، نرخ روز از پنل داده شود
            'decimals' => 2,
            'is_default' => false,
            'is_active' => false,
            'position' => 20,
        ]);
    }
}
