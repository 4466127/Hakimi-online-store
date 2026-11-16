<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * دسته‌های اولیه به الگوی منوی دیجی‌کالا، با کلمه‌های افغانستانی (بوت، بکس، جاکت، پکه، کمره، قرطاسیه…).
 * بی خودرو، طلا و ابزارآلات؛ «سلامت و پزشکی» برداشته و «کتاب، قرطاسیه و هنر» و «لوازم ورزشی»
 * اضافه شد (به خواست صاحب پروژه). نرخ کمیسیون پیش‌فرض صاحب پروژه روی دسته‌هایی که نرخ ویژه
 * دارند (بقیه = پیش‌فرض ۷٪ تنظیمات).
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
    public const TREE = [
        ['موبایل و لوازم', 'mobile', 'device-phone-mobile', null, [
            ['موبایل', 'mobile-phones', [
                ['موبایل سامسونگ', 'samsung-phones'],
                ['موبایل شیائومی', 'xiaomi-phones'],
                ['موبایل اپل (آیفون)', 'apple-phones'],
                ['موبایل ساده (دکمه‌ای)', 'feature-phones'],
            ], 3],
            ['لوازم جانبی موبایل', 'mobile-accessories', [
                ['چارجر', 'chargers'],
                ['کیبل', 'cables'],
                ['پوش موبایل', 'phone-cases'],
                ['گلس موبایل', 'screen-protectors'],
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
        ['وسایل دیجیتال', 'digital', 'tv', null, [
            ['تبلت', 'tablets', [], null],
            ['ساعت و بند هوشمند', 'smartwatches', [], null],
            ['اسپیکر و سیستم صوتی', 'speakers', [], null],
            ['کمره', 'cameras', [], null],
            ['کنسول و بازی', 'gaming', [], null],
        ]],
        ['بوت و کفش', 'shoes', 'shopping-bag', 7, [
            ['بوت مردانه', 'mens-shoes', [
                ['بوت رسمی مردانه', 'mens-formal-shoes'],
                ['بوت اسپورت مردانه', 'mens-sport-shoes'],
                ['موزه (بوت ساق‌دار) مردانه', 'mens-boots'],
                ['چپلی', 'chaplis'],
            ], null],
            ['بوت زنانه', 'womens-shoes', [
                ['بوت رسمی زنانه', 'womens-formal-shoes'],
                ['بوت اسپورت زنانه', 'womens-sport-shoes'],
                ['موزه (بوت ساق‌دار) زنانه', 'womens-boots'],
                ['سندل و چپلک', 'sandals'],
            ], null],
            ['بوت بچه‌گانه', 'kids-shoes', [], null],
        ]],
        ['لباس هزارگی و افغانی', 'clothing', 'sparkles', 7, [
            ['لباس زنانه', 'womens-clothing', [
                ['لباس هزارگی زنانه', 'womens-traditional'],
                ['لباس افغانی زنانه', 'womens-afghan'],
                ['چادر و حجاب', 'hijab'],
                ['جاکت و بالاپوش زنانه', 'womens-jackets'],
                ['پیراهن و دامن زنانه', 'womens-dresses'],
            ], null],
            ['لباس مردانه', 'mens-clothing', [
                ['لباس هزارگی مردانه', 'mens-hazaragi'],
                ['پیراهن تنبان', 'perahan-tunban'],
                ['واسکت', 'waskat'],
                ['چپن', 'chapan'],
                ['کلاه و پکول', 'hats'],
                ['جاکت و بالاپوش مردانه', 'mens-jackets'],
                ['پیراهن و تی‌شرت مردانه', 'mens-shirts'],
                ['پتلون مردانه', 'mens-pants'],
            ], null],
            ['لباس بچه‌گانه', 'kids-clothing', [
                ['لباس نوزاد', 'baby-clothing'],
                ['لباس پسرانه', 'boys-clothing'],
                ['لباس دخترانه', 'girls-clothing'],
            ], null],
            ['بکس و اکسسوری', 'bags-accessories', [
                ['بکس زنانه', 'womens-bags'],
                ['بکس مردانه و کمربند', 'mens-bags-belts'],
                ['ساعت دستی', 'wrist-watches'],
                ['عینک آفتابی', 'sunglasses'],
                ['شال و دستمال سر', 'scarves'],
            ], 7],
        ]],
        ['اجناس وطنی و محلی', 'local-products', 'gift', null, [
            ['صنایع دستی', 'handicrafts', [
                ['قالین و گلیم', 'carpets-rugs'],
                ['گلدوزی و خامک‌دوزی', 'embroidery'],
                ['ظروف سنگی و لاجورد', 'lapis-stone'],
                ['زیورات محلی', 'traditional-jewelry'],
            ], null],
            ['خوراکی وطنی', 'local-food', [
                ['خشکبار (کشمش، بادام، چارمغز)', 'dried-fruits-nuts'],
                ['زعفران', 'saffron'],
                ['چای و ادویه', 'tea-spices'],
            ], null],
        ]],
        ['خانه و آشپزخانه', 'home-kitchen', 'home-modern', null, [
            ['ظروف آشپزخانه', 'cookware', [
                ['دیگ و دیگچه', 'pots-pans'],
                ['ظروف چای و دسترخوان', 'tea-sets'],
                ['ظروف نگهداری', 'storage-containers'],
            ], null],
            ['مفروشات و دکور', 'home-decor', [
                ['پرده', 'curtains'],
                ['توشک، بالشت و کمپل', 'bedding'],
                ['دکور خانه', 'decor'],
            ], null],
            ['صفایی و شستشو', 'cleaning', [], null],
        ]],
        ['وسایل برقی خانه', 'home-appliances', 'light-bulb', null, [
            ['وسایل برقی آشپزخانه', 'kitchen-appliances', [
                ['سماوار و چای‌جوش برقی', 'kettles'],
                ['مکسر و جوس‌گیر', 'blenders'],
            ], null],
            ['بخاری و پکه', 'heating-cooling', [
                ['بخاری برقی', 'electric-heaters'],
                ['پکه', 'fans'],
            ], null],
            ['اتو و ماشین لباسشویی', 'irons', [], null],
            ['تلویزیون', 'televisions', [], null],
        ]],
        ['آرایشی و صحی', 'beauty', 'heart', null, [
            ['وسایل آرایش', 'makeup', [], null],
            ['وسایل صحی شخصی', 'personal-care', [], null],
            ['عطر', 'perfumes', [], null],
        ]],
        ['کتاب، قرطاسیه و هنر', 'books-art', 'book-open', null, [
            ['کتاب', 'books', [
                ['کتاب درسی و آموزشی', 'textbooks'],
                ['رمان و ادبیات', 'literature'],
                ['کتاب دینی', 'religious-books'],
                ['کتاب کودک', 'kids-books'],
            ], null],
            ['قرطاسیه', 'stationery', [
                ['کتابچه و دفتر', 'notebooks'],
                ['قلم و پنسل', 'pens-pencils'],
                ['بکس مکتب', 'school-bags'],
            ], null],
            ['هنر و خطاطی', 'art-supplies', [
                ['رنگ و وسایل نقاشی', 'painting-supplies'],
                ['وسایل خطاطی', 'calligraphy'],
                ['تابلو و نقاشی', 'paintings'],
            ], null],
        ]],
        ['لوازم ورزشی', 'sports', 'trophy', null, [
            ['لباس ورزشی', 'sportswear', [], null],
            ['توپ و وسایل بازی', 'balls-games', [], null],
            ['وسایل بدنسازی', 'fitness', [], null],
            ['بایسکل', 'bicycles', [], null],
            ['پکنیک و سفر', 'outdoor', [], null],
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
