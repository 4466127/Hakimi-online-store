<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تصمیم‌های صاحب پروژه (۲۶ عقرب ۱۴۰۵):
 * - کلمه‌های افغانستانی به‌جای ایرانی در نام دسته‌ها؛ «لباس افغانی و مُد» ← «لباس هزارگی و افغانی».
 * - «سلامت و پزشکی» برداشته شود («کتاب، قرطاسیه و هنر» و «لوازم ورزشی» را CategorySeeder می‌سازد).
 * - تحویل فعلاً فقط در کابل (غزنی خاموش).
 * - صفحه‌ی «مرجوعی و ضمانت» ← «برگشت جنس و ضمانت».
 *
 * فقط داده را درست می‌کند و هیچ چیزی را برای همیشه پاک نمی‌کند: نامی که مدیر خودش عوض کرده
 * دست نمی‌خورد، و دسته‌های «سلامت» فقط به سطل زباله می‌روند (از پنل برمی‌گردند).
 */
return new class extends Migration
{
    /** آدرس => [نام قدیم، نام تازه] */
    private const RENAMES = [
        'mobile-phones' => ['گوشی موبایل', 'موبایل'],
        'samsung-phones' => ['گوشی سامسونگ', 'موبایل سامسونگ'],
        'xiaomi-phones' => ['گوشی شیائومی', 'موبایل شیائومی'],
        'apple-phones' => ['گوشی اپل (آیفون)', 'موبایل اپل (آیفون)'],
        'feature-phones' => ['گوشی ساده (دکمه‌ای)', 'موبایل ساده (دکمه‌ای)'],
        'phone-cases' => ['پوش (قاب)', 'پوش موبایل'],
        'screen-protectors' => ['گلس (محافظ صفحه)', 'گلس موبایل'],
        'digital' => ['کالای دیجیتال', 'وسایل دیجیتال'],
        'cameras' => ['دوربین', 'کمره'],
        'mens-shoes' => ['کفش مردانه', 'بوت مردانه'],
        'mens-formal-shoes' => ['کفش رسمی مردانه', 'بوت رسمی مردانه'],
        'mens-sport-shoes' => ['کفش اسپورت مردانه', 'بوت اسپورت مردانه'],
        'mens-boots' => ['بوت مردانه', 'موزه (بوت ساق‌دار) مردانه'],
        'womens-shoes' => ['کفش زنانه', 'بوت زنانه'],
        'womens-formal-shoes' => ['کفش رسمی زنانه', 'بوت رسمی زنانه'],
        'womens-sport-shoes' => ['کفش اسپورت زنانه', 'بوت اسپورت زنانه'],
        'womens-boots' => ['بوت زنانه', 'موزه (بوت ساق‌دار) زنانه'],
        'sandals' => ['سندل', 'سندل و چپلک'],
        'kids-shoes' => ['کفش بچه‌گانه', 'بوت بچه‌گانه'],
        'clothing' => ['لباس افغانی و مُد', 'لباس هزارگی و افغانی'],
        'womens-traditional' => ['لباس محلی زنانه', 'لباس هزارگی زنانه'],
        'womens-jackets' => ['کاپشن و پالتو زنانه', 'جاکت و بالاپوش زنانه'],
        'womens-dresses' => ['مانتو و پیراهن زنانه', 'پیراهن و دامن زنانه'],
        'mens-jackets' => ['کاپشن و پالتو مردانه', 'جاکت و بالاپوش مردانه'],
        'bags-accessories' => ['کیف و اکسسوری', 'بکس و اکسسوری'],
        'womens-bags' => ['کیف زنانه', 'بکس زنانه'],
        'mens-bags-belts' => ['کیف مردانه و کمربند', 'بکس مردانه و کمربند'],
        'wrist-watches' => ['ساعت مچی', 'ساعت دستی'],
        'scarves' => ['شال و روسری', 'شال و دستمال سر'],
        'local-products' => ['محصولات بومی و محلی', 'اجناس وطنی و محلی'],
        'local-food' => ['خوراکی محلی', 'خوراکی وطنی'],
        'pots-pans' => ['دیگ و قابلمه', 'دیگ و دیگچه'],
        'cleaning' => ['شستشو و نظافت', 'صفایی و شستشو'],
        'home-appliances' => ['لوازم خانگی برقی', 'وسایل برقی خانه'],
        'kitchen-appliances' => ['لوازم برقی آشپزخانه', 'وسایل برقی آشپزخانه'],
        'blenders' => ['مخلوط‌کن و آب‌میوه‌گیری', 'مکسر و جوس‌گیر'],
        'heating-cooling' => ['گرمایشی و سرمایشی', 'بخاری و پکه'],
        'fans' => ['پکه (پنکه)', 'پکه'],
        'irons' => ['اتو و لوازم شستشو', 'اتو و ماشین لباسشویی'],
        'beauty' => ['آرایشی و بهداشتی', 'آرایشی و صحی'],
        'makeup' => ['لوازم آرایشی', 'وسایل آرایش'],
        'personal-care' => ['بهداشت شخصی', 'وسایل صحی شخصی'],
        'perfumes' => ['عطر و ادکلن', 'عطر'],
    ];

    /** صفحه‌های ثابت: آدرس => [عنوان قدیم، عنوان تازه] */
    private const PAGE_RENAMES = [
        'returns' => ['مرجوعی و ضمانت', 'برگشت جنس و ضمانت'],
    ];

    /** ترتیب تازه (زنانه پیش از مردانه، و جا برای زیردسته‌های تازه). آدرس => [ترتیب قدیم، ترتیب تازه] */
    private const POSITIONS = [
        'womens-clothing' => [1, 0],
        'mens-clothing' => [0, 1],
        'hijab' => [1, 2],
        'womens-jackets' => [2, 3],
        'womens-dresses' => [3, 4],
        'perahan-tunban' => [0, 1],
        'waskat' => [1, 2],
        'chapan' => [2, 3],
        'hats' => [3, 4],
        'mens-jackets' => [4, 5],
        'mens-shirts' => [5, 6],
        'mens-pants' => [6, 7],
    ];

    public function up(): void
    {
        $this->rename('categories', 'name', self::RENAMES);
        $this->rename('pages', 'title', self::PAGE_RENAMES);

        foreach (self::POSITIONS as $slug => [$old, $new]) {
            DB::table('categories')->where('slug', $slug)->where('position', $old)->update(['position' => $new]);
        }

        // «سلامت و پزشکی» و همه‌ی زیردسته‌هایش به سطل زباله
        $health = DB::table('categories')->where('slug', 'health')->whereNull('deleted_at')->first();
        if ($health !== null) {
            DB::table('categories')
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('id', $health->id)
                    ->orWhere('path', (string) $health->id)
                    ->orWhere('path', 'like', $health->id.'/%'))
                ->update(['deleted_at' => now(), 'is_active' => false]);
        }

        // تحویل فعلاً فقط در کابل
        DB::table('provinces')->where('code', 'GHA')->update(['delivery_enabled' => false]);

        Cache::forget('categories.menu');
        Cache::forget('provinces.delivery');
    }

    public function down(): void
    {
        $flip = fn (array $map) => array_map(fn (array $pair) => [$pair[1], $pair[0]], $map);
        $this->rename('categories', 'name', $flip(self::RENAMES));
        $this->rename('pages', 'title', $flip(self::PAGE_RENAMES));

        foreach (self::POSITIONS as $slug => [$old, $new]) {
            DB::table('categories')->where('slug', $slug)->where('position', $new)->update(['position' => $old]);
        }

        $health = DB::table('categories')->where('slug', 'health')->first();
        if ($health !== null) {
            DB::table('categories')
                ->where(fn ($q) => $q->where('id', $health->id)
                    ->orWhere('path', (string) $health->id)
                    ->orWhere('path', 'like', $health->id.'/%'))
                ->update(['deleted_at' => null, 'is_active' => true]);
        }

        DB::table('provinces')->where('code', 'GHA')->update(['delivery_enabled' => true]);

        Cache::forget('categories.menu');
        Cache::forget('provinces.delivery');
    }

    /** @param array<string, array{0: string, 1: string}> $map آدرس => [نام فعلی، نام تازه] */
    private function rename(string $table, string $column, array $map): void
    {
        $rows = DB::table($table)->whereIn('slug', array_keys($map))->get(['id', 'slug', $column]);

        foreach ($rows as $row) {
            $name = json_decode((string) $row->{$column}, true) ?: [];
            [$from, $to] = $map[$row->slug];

            // اگر مدیر نام را خودش عوض کرده، دست نزن
            if (($name['fa'] ?? null) !== $from) {
                continue;
            }

            $name['fa'] = $to;
            DB::table($table)->where('id', $row->id)
                ->update([$column => json_encode($name, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
