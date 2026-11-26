<?php

use App\Services\Payments\Gateways\HesabPayGateway;
use App\Services\Payments\Gateways\LogGateway;

/*
|--------------------------------------------------------------------------
| تنظیمات ثابت فروشگاه
|--------------------------------------------------------------------------
| چیزهایی که مدیر از پنل عوض می‌کند (نام، تلفن، واتساپ، آدرس، لوگو) در جدول
| settings است، نه اینجا. اینجا فقط پیش‌فرض‌ها و چیزهای فنی است.
*/

return [

    /*
     | نقشه‌ی انتخاب موقعیت تحویل (Leaflet؛ کتابخانه داخل سایت است، فقط کاشی‌های نقشه از سرور نقشه می‌آید).
     | پیش‌فرض: کاشی‌های استاندارد OpenStreetMap — طبق «Tile Usage Policy» برای دیدن عادی کاربر مجاز است،
     | با نام OpenStreetMap روی نقشه؛ ولی تضمینی ندارد و ممکن است بی خبر بسته شود. اگر فروش زیاد شد،
     | MAP_TILE_URL و MAP_ATTRIBUTION را به یک سرویس پولی (مثلاً MapTiler) یا سرور خودمان عوض کنید.
     | MAP_ENABLED=false ← دکمه‌ی نقشه پنهان؛ آدرس نوشتاری همیشه کار می‌کند.
     */
    'maps' => [
        'enabled' => (bool) env('MAP_ENABLED', true),
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'),
        'max_zoom' => (int) env('MAP_MAX_ZOOM', 19),
        // مرکز اول نقشه: کابل
        'center' => [34.5553, 69.2075],
        'zoom' => 13,
        // مرز تقریبی افغانستان: موقعیت بیرون از آن پذیرفته نمی‌شود
        'bounds' => ['lat' => [29.3, 38.6], 'lng' => [60.4, 75.0]],
    ],

    // بنرهای صفحه‌ی اصلی: هر چند میلی‌ثانیه بنر بعدی (خواست صاحب پروژه: ۱٫۵ ثانیه، حلقوی)
    'home' => [
        'banner_interval_ms' => (int) env('BANNER_INTERVAL_MS', 1500),
    ],

    // زمان‌ها در دیتابیس UTC ذخیره می‌شود و به وقت کابل نشان داده می‌شود.
    'timezone' => env('STORE_TIMEZONE', 'Asia/Kabul'),

    // پول پیش‌فرض. قیمت‌ها همیشه به افغانی ذخیره می‌شود.
    'currency' => 'AFN',

    // زبان محتوا وقتی ترجمه‌ی زبان انتخاب‌شده نیست.
    'content_fallback_locale' => 'fa',

    // زبان‌های راست‌چین.
    'rtl_locales' => ['fa', 'ps'],

    // عکس جنس: از هر اندازه نسخه‌ی AVIF هم ساخته شود (فقط اگر سرور پشتیبانی کند؛ خودکار بررسی می‌شود).
    'images' => [
        'avif' => true,
    ],

    // تحویل حضوری: روزهایی که مشتری می‌تواند برای گرفتن جنس انتخاب کند (اگر هیچ منطقه‌ی تحویلی نبود).
    // بی کرایه است؛ فقط برای آماده شدن جنس در نمایندگی روز گرفته می‌شود.
    'pickup' => [
        'days' => 5,
        'cutoff_hour' => 16,
        'days_off' => [5],        // جمعه (شماره‌ی ISO روز هفته)
    ],

    /*
     * پرداخت آنلاین: درایور هر درگاه (مرحله‌ی ۵).
     *
     * `log` هیچ پولی جابه‌جا نمی‌کند و می‌گوید «آماده نیست». `hesabpay` از روی مستندات رسمی
     * ساخته شد (۳ قوس ۱۴۰۵) و فقط وقتی «آماده» است که HESABPAY_API_KEY در `.env` باشد؛ مدیر هم
     * باید روش را در پنل روشن کند. جزئیات و کارهای مانده: docs/hesabpay.md
     */
    'payments' => [
        'drivers' => [
            'log' => LogGateway::class,
            'hesabpay' => HesabPayGateway::class,
        ],
        // سفارش آنلاینی که تا این چند دقیقه پرداخت نشد، خودکار لغو و موجودی آزاد می‌شود
        // (نشست حساب‌پی ۱۵ دقیقه است؛ پیام‌های دیرآمده‌ی حساب‌پی تا حدود ۵۰ دقیقه بعد هم می‌رسد).
        'online_cancel_after_minutes' => (int) env('ONLINE_PAYMENT_CANCEL_MINUTES', 120),
    ],

    // سبد خرید: بیشترین تعداد یک نوع در سبد (و سفارش)؛ اگر موجودی کمتر بود موجودی.
    'cart' => [
        'max_qty_per_line' => 20,
    ],

    /*
     * امنیت پنل (مرحله‌ی ۸). ورود دومرحله‌ای همیشه از «حساب من» قابل روشن کردن است؛
     * با true هر کاربر پنل پیش از هر کار مجبور می‌شود آن را بسازد (پیشنهاد برای سرور، بعد از آموزش خانواده).
     */
    'security' => [
        'admin_mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', false),
    ],

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

        // تحویل حضوری از دفتر مرکزی (آدرس همان «آدرس» فروشگاه)، بی کرایه
        'delivery.pickup_enabled' => true,
        'delivery.pickup_note' => null,

        // سقف سفارش هر مشتری در ۲۴ ساعت (جلوی سفارش‌های اذیتی و پر کردن دیتابیس)؛ ۰ = بی سقف
        'orders.max_per_day' => 2,

        // بازارگاه (فروشندگان): از پنل «تنظیمات بازارگاه» عوض می‌شود
        'marketplace.default_commission_rate' => 7,        // «سایر اجناس» و هر دسته‌ای که نرخ ندارد
        'marketplace.default_settlement_cycle' => 'monthly',
        'marketplace.social_free_commission_days' => 90,   // فروشنده‌ی اینستاگرامی: ۳ ماه کمیسیون ۰٪
        'marketplace.brand_free_pickup_days' => 90,        // صاحب برند: ۳ ماه گرفتن رایگان جنس از دکان
        'marketplace.brand_settlement_cycle' => 'weekly',  // صاحب برند: تسویه‌ی هفته‌وار
        'marketplace.local_producer_rate' => 3,            // تولیدکننده‌ی محلی: کمیسیون ۳٪ …
        'marketplace.local_producer_days' => 90,           // … برای ۳ ماه
        'marketplace.settlement_hold_days' => 0,           // روزهای انتظار پیش از قابل تسویه شدن طلب فروشنده (برای برگشت جنس)
    ],

    // مشخصات مدیر اول که seeder می‌سازد. رمز در اولین ورود باید عوض شود.
    'initial_owner' => [
        'name' => env('STORE_OWNER_NAME', 'مدیر فروشگاه'),
        'phone' => env('STORE_OWNER_PHONE', '0700000000'),
        'password' => env('STORE_OWNER_PASSWORD'),
    ],

];
