<?php

/*
|--------------------------------------------------------------------------
| تنظیمات ثابت فروشگاه
|--------------------------------------------------------------------------
| چیزهایی که مدیر از پنل عوض می‌کند (نام، تلفن، واتساپ، آدرس، لوگو) در جدول
| settings است، نه اینجا. اینجا فقط پیش‌فرض‌ها و چیزهای فنی است.
*/

return [

    // زمان‌ها در دیتابیس UTC ذخیره می‌شود و به وقت کابل نشان داده می‌شود.
    'timezone' => env('STORE_TIMEZONE', 'Asia/Kabul'),

    // پول پیش‌فرض. قیمت‌ها همیشه به افغانی ذخیره می‌شود.
    'currency' => 'AFN',

    // زبان محتوا وقتی ترجمه‌ی زبان انتخاب‌شده نیست.
    'content_fallback_locale' => 'fa',

    // زبان‌های راست‌چین.
    'rtl_locales' => ['fa', 'ps'],

    // قفل حساب مدیر بعد از این تعداد رمز اشتباه، برای این چند دقیقه.
    'admin_lock_after_attempts' => 10,
    'admin_lock_minutes' => 15,

    // مقدار اولیه‌ی تنظیمات فروشگاه (وقتی هنوز چیزی در پنل ذخیره نشده).
    'defaults' => [
        'store.name' => 'بازارک',
        'store.tagline' => 'بازار آنلاین افغانستان — خرید آسان، تحویل درِ خانه',
        'store.phone' => null,
        'store.whatsapp' => null,
        'store.email' => null,
        'store.address' => null,
        'store.logo' => null,
        'store.is_open' => false,

        // بازارگاه (فروشندگان): از پنل «تنظیمات بازارگاه» عوض می‌شود
        'marketplace.default_commission_rate' => 7,        // «سایر اجناس» و هر دسته‌ای که نرخ ندارد
        'marketplace.default_settlement_cycle' => 'monthly',
        'marketplace.social_free_commission_days' => 90,   // فروشنده‌ی اینستاگرامی: ۳ ماه کمیسیون ۰٪
        'marketplace.brand_free_pickup_days' => 90,        // صاحب برند: ۳ ماه گرفتن رایگان جنس از دکان
        'marketplace.brand_settlement_cycle' => 'weekly',  // صاحب برند: تسویه‌ی هفته‌وار
        'marketplace.local_producer_rate' => 3,            // تولیدکننده‌ی محلی: کمیسیون ۳٪ …
        'marketplace.local_producer_days' => 90,           // … برای ۳ ماه
    ],

    // مشخصات مدیر اول که seeder می‌سازد. رمز در اولین ورود باید عوض شود.
    'initial_owner' => [
        'name' => env('STORE_OWNER_NAME', 'مدیر فروشگاه'),
        'phone' => env('STORE_OWNER_PHONE', '0700000000'),
        'password' => env('STORE_OWNER_PASSWORD'),
    ],

];
