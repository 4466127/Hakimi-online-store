<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * دسته‌های اولیه به الگوی منوی دیجی‌کالا (بی خودرو، طلا و ابزارآلات — به خواست صاحب پروژه)،
 * با نرخ کمیسیون پیش‌فرض صاحب پروژه روی دسته‌هایی که نرخ ویژه دارند (بقیه = پیش‌فرض ۷٪ تنظیمات).
 *
 * فقط داده‌ی شروع است: مدیر از پنل همه را عوض، اضافه یا خاموش می‌کند. چند بار اجرا
 * کردنش بی‌خطر است: دسته‌ی موجود (با همان آدرس) دوباره ساخته یا تغییر داده نمی‌شود.
 */
class CategorySeeder extends Seeder
{
    /**
     * دسته‌ی اصلی: [نام، آدرس، آیکون، کمیسیون٪|null، گروه‌ها]
     * گروه:        [نام، آدرس، زیردسته‌ها، کمیسیون٪|null]
     * زیردسته:     [نام، آدرس]
     */
    private const TREE = [
        ['موبایل و لوازم', 'mobile', 'device-phone-mobile', null, [
            ['گوشی موبایل', 'mobile-phones', [
                ['گوشی سامسونگ', 'samsung-phones'],
                ['گوشی شیائومی', 'xiaomi-phones'],
                ['گوشی اپل (آیفون)', 'apple-phones'],
                ['گوشی ساده (دکمه‌ای)', 'feature-phones'],
            ], 3],
            ['لوازم جانبی موبایل', 'mobile-accessories', [
                ['چارجر', 'chargers'],
                ['کیبل', 'cables'],
                ['پوش (قاب)', 'phone-cases'],
                ['گلس (محافظ صفحه)', 'screen-protectors'],
                ['پاوربانک', 'power-banks'],
                ['گوشکی و هدفون', 'headphones'],
            ], 6],
        ]],
        ['لپتاپ و کمپیوتر', 'computers', 'computer-desktop', 4, [
            ['لپتاپ', 'laptops', [
                ['لپتاپ نو', 'new-laptops'],
                ['لپتاپ کارکرده', 'used-laptops'],
            ], null],
            ['لوازم کمپیوتر', 'computer-accessories', [
                ['ماوس و کیبورد', 'mouse-keyboard'],
                ['فلش و هارد', 'storage-drives'],
                ['بکس لپتاپ', 'laptop-bags'],
                ['چارجر لپتاپ', 'laptop-chargers'],
            ], 6],
        ]],
        ['کالای دیجیتال', 'digital', 'tv', null, [
            ['تبلت', 'tablets', [], null],
            ['ساعت و بند هوشمند', 'smartwatches', [], null],
            ['اسپیکر و سیستم صوتی', 'speakers', [], null],
            ['دوربین', 'cameras', [], null],
            ['کنسول و بازی', 'gaming', [], null],
        ]],
        ['بوت و کفش', 'shoes', 'shopping-bag', 7, [
            ['کفش مردانه', 'mens-shoes', [
                ['کفش رسمی مردانه', 'mens-formal-shoes'],
                ['کفش اسپورت مردانه', 'mens-sport-shoes'],
                ['بوت مردانه', 'mens-boots'],
                ['چپلی', 'chaplis'],
            ], null],
            ['کفش زنانه', 'womens-shoes', [
                ['کفش رسمی زنانه', 'womens-formal-shoes'],
                ['کفش اسپورت زنانه', 'womens-sport-shoes'],
                ['بوت زنانه', 'womens-boots'],
                ['سندل', 'sandals'],
            ], null],
            ['کفش بچه‌گانه', 'kids-shoes', [], null],
        ]],
        ['لباس افغانی و مُد', 'clothing', 'sparkles', 7, [
            ['لباس مردانه', 'mens-clothing', [
                ['پیراهن تنبان', 'perahan-tunban'],
                ['واسکت', 'waskat'],
                ['چپن', 'chapan'],
                ['کلاه و پکول', 'hats'],
                ['کاپشن و پالتو مردانه', 'mens-jackets'],
                ['پیراهن و تی‌شرت مردانه', 'mens-shirts'],
                ['پتلون مردانه', 'mens-pants'],
            ], null],
            ['لباس زنانه', 'womens-clothing', [
                ['لباس محلی زنانه', 'womens-traditional'],
                ['چادر و حجاب', 'hijab'],
                ['کاپشن و پالتو زنانه', 'womens-jackets'],
                ['مانتو و پیراهن زنانه', 'womens-dresses'],
            ], null],
            ['لباس بچه‌گانه', 'kids-clothing', [
                ['لباس نوزاد', 'baby-clothing'],
                ['لباس پسرانه', 'boys-clothing'],
                ['لباس دخترانه', 'girls-clothing'],
            ], null],
            ['کیف و اکسسوری', 'bags-accessories', [
                ['کیف زنانه', 'womens-bags'],
                ['کیف مردانه و کمربند', 'mens-bags-belts'],
                ['ساعت مچی', 'wrist-watches'],
                ['عینک آفتابی', 'sunglasses'],
                ['شال و روسری', 'scarves'],
            ], 7],
        ]],
        ['محصولات بومی و محلی', 'local-products', 'gift', null, [
            ['صنایع دستی', 'handicrafts', [
                ['قالین و گلیم', 'carpets-rugs'],
                ['گلدوزی و خامک‌دوزی', 'embroidery'],
                ['ظروف سنگی و لاجورد', 'lapis-stone'],
                ['زیورات محلی', 'traditional-jewelry'],
            ], null],
            ['خوراکی محلی', 'local-food', [
                ['خشکبار (کشمش، بادام، چارمغز)', 'dried-fruits-nuts'],
                ['زعفران', 'saffron'],
                ['چای و ادویه', 'tea-spices'],
            ], null],
        ]],
        ['خانه و آشپزخانه', 'home-kitchen', 'home-modern', null, [
            ['ظروف آشپزخانه', 'cookware', [
                ['دیگ و قابلمه', 'pots-pans'],
                ['ظروف چای و دسترخوان', 'tea-sets'],
                ['ظروف نگهداری', 'storage-containers'],
            ], null],
            ['مفروشات و دکور', 'home-decor', [
                ['پرده', 'curtains'],
                ['توشک، بالشت و کمپل', 'bedding'],
                ['دکور خانه', 'decor'],
            ], null],
            ['شستشو و نظافت', 'cleaning', [], null],
        ]],
        ['لوازم خانگی برقی', 'home-appliances', 'light-bulb', null, [
            ['لوازم برقی آشپزخانه', 'kitchen-appliances', [
                ['سماوار و چای‌جوش برقی', 'kettles'],
                ['مخلوط‌کن و آب‌میوه‌گیری', 'blenders'],
            ], null],
            ['گرمایشی و سرمایشی', 'heating-cooling', [
                ['بخاری برقی', 'electric-heaters'],
                ['پکه (پنکه)', 'fans'],
            ], null],
            ['اتو و لوازم شستشو', 'irons', [], null],
            ['تلویزیون', 'televisions', [], null],
        ]],
        ['آرایشی و بهداشتی', 'beauty', 'heart', null, [
            ['لوازم آرایشی', 'makeup', [], null],
            ['بهداشت شخصی', 'personal-care', [], null],
            ['عطر و ادکلن', 'perfumes', [], null],
        ]],
        ['سلامت و پزشکی', 'health', 'beaker', null, [
            ['لوازم پزشکی خانگی', 'home-medical', [
                ['فشارسنج', 'blood-pressure-monitors'],
                ['ترمامتر', 'thermometers'],
            ], null],
            ['ماسک و دستکش', 'masks-gloves', [], null],
        ]],
    ];

    public function run(): void
    {
        foreach (self::TREE as $rootPosition => [$name, $slug, $icon, $rate, $groups]) {
            $root = $this->ensure($slug, $name, null, $rootPosition, $icon, $rate);
            foreach ($groups as $groupPosition => [$groupName, $groupSlug, $items, $groupRate]) {
                $group = $this->ensure($groupSlug, $groupName, $root, $groupPosition, null, $groupRate);
                foreach ($items as $itemPosition => [$itemName, $itemSlug]) {
                    $this->ensure($itemSlug, $itemName, $group, $itemPosition);
                }
            }
        }
    }

    private function ensure(string $slug, string $name, ?Category $parent, int $position, ?string $icon = null, int|float|null $rate = null): Category
    {
        $existing = Category::withTrashed()->where('slug', $slug)->first();
        if ($existing !== null) {
            return $existing;
        }

        $category = new Category([
            'slug' => $slug,
            'name' => ['fa' => $name],
            'parent_id' => $parent?->id,
            'position' => $position,
            'icon' => $icon,
            'is_active' => true,
            'show_in_menu' => true,
        ]);
        $category->commission_rate = $rate;
        $category->save();

        return $category;
    }
}
