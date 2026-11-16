<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * داده‌ی اولیه‌ای که همیشه لازم است (روی لپتاپ و روی سرور).
 * همه‌ی seederها «بی‌خطر برای تکرار» هستند: هیچ داده‌ی موجودی را عوض یا پاک نمی‌کنند.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            ProvinceSeeder::class,
            PageSeeder::class,
            CategorySeeder::class,
            CatalogAttributeSeeder::class,
            OwnerSeeder::class,
        ]);
    }
}
