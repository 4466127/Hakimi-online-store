<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * دسته‌های اولیه (چهار دسته‌ی اصلی خواست صاحب پروژه، با گروه‌ها و زیردسته‌ها).
 *
 * فقط داده‌ی شروع است: مدیر از پنل همه را عوض، اضافه یا خاموش می‌کند. چند بار اجرا
 * کردنش بی‌خطر است: دسته‌ی موجود (با همان آدرس) دوباره ساخته یا تغییر داده نمی‌شود.
 */
class CategorySeeder extends Seeder
{
    /** [نام، آدرس، آیکون، [گروه‌ها: [نام، آدرس، [زیردسته‌ها: [نام، آدرس]]]]] */
    private const TREE = [
        ['موبایل و لوازم', 'mobile', 'device-phone-mobile', [
            ['گوشی موبایل', 'mobile-phones', [
                ['گوشی سامسونگ', 'samsung-phones'],
                ['گوشی شیائومی', 'xiaomi-phones'],
                ['گوشی اپل (آیفون)', 'apple-phones'],
                ['گوشی ساده (دکمه‌ای)', 'feature-phones'],
            ]],
            ['لوازم جانبی موبایل', 'mobile-accessories', [
                ['چارجر', 'chargers'],
                ['کیبل', 'cables'],
                ['پوش (قاب)', 'phone-cases'],
                ['گلس (محافظ صفحه)', 'screen-protectors'],
                ['پاوربانک', 'power-banks'],
                ['گوشکی و هدفون', 'headphones'],
            ]],
        ]],
        ['لپتاپ و کمپیوتر', 'computers', 'computer-desktop', [
            ['لپتاپ', 'laptops', [
                ['لپتاپ نو', 'new-laptops'],
                ['لپتاپ کارکرده', 'used-laptops'],
            ]],
            ['لوازم کمپیوتر', 'computer-accessories', [
                ['ماوس و کیبورد', 'mouse-keyboard'],
                ['فلش و هارد', 'storage-drives'],
                ['بکس لپتاپ', 'laptop-bags'],
                ['چارجر لپتاپ', 'laptop-chargers'],
            ]],
        ]],
        ['بوت و کفش', 'shoes', 'shopping-bag', [
            ['کفش مردانه', 'mens-shoes', [
                ['کفش رسمی مردانه', 'mens-formal-shoes'],
                ['کفش اسپورت مردانه', 'mens-sport-shoes'],
                ['بوت مردانه', 'mens-boots'],
                ['چپلی', 'chaplis'],
            ]],
            ['کفش زنانه', 'womens-shoes', [
                ['کفش رسمی زنانه', 'womens-formal-shoes'],
                ['کفش اسپورت زنانه', 'womens-sport-shoes'],
                ['بوت زنانه', 'womens-boots'],
                ['سندل', 'sandals'],
            ]],
            ['کفش بچه‌گانه', 'kids-shoes', []],
        ]],
        ['لباس افغانی و مُد', 'clothing', 'sparkles', [
            ['لباس مردانه', 'mens-clothing', [
                ['پیراهن تنبان', 'perahan-tunban'],
                ['واسکت', 'waskat'],
                ['چپن', 'chapan'],
                ['کلاه و پکول', 'hats'],
            ]],
            ['لباس زنانه', 'womens-clothing', [
                ['لباس محلی زنانه', 'womens-traditional'],
                ['چادر و حجاب', 'hijab'],
            ]],
            ['لباس بچه‌گانه', 'kids-clothing', []],
        ]],
    ];

    public function run(): void
    {
        foreach (self::TREE as $rootPosition => [$name, $slug, $icon, $groups]) {
            $root = $this->ensure($slug, $name, null, $rootPosition, $icon);
            foreach ($groups as $groupPosition => [$groupName, $groupSlug, $items]) {
                $group = $this->ensure($groupSlug, $groupName, $root, $groupPosition);
                foreach ($items as $itemPosition => [$itemName, $itemSlug]) {
                    $this->ensure($itemSlug, $itemName, $group, $itemPosition);
                }
            }
        }
    }

    private function ensure(string $slug, string $name, ?Category $parent, int $position, ?string $icon = null): Category
    {
        $existing = Category::withTrashed()->where('slug', $slug)->first();
        if ($existing !== null) {
            return $existing;
        }

        return Category::create([
            'slug' => $slug,
            'name' => ['fa' => $name],
            'parent_id' => $parent?->id,
            'position' => $position,
            'icon' => $icon,
            'is_active' => true,
            'show_in_menu' => true,
        ]);
    }
}
