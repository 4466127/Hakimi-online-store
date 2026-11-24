<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * تکمیل زیردسته‌ها و مشخصه‌های هر زیردسته به الگوی بازار افغانستان (خواست صاحب پروژه، ۳ قوس ۱۴۰۵).
 *
 * - دسته‌ی اصلی تازه ساخته نمی‌شود؛ فقط زیردسته زیر دسته‌های موجود.
 * - هیچ جنس، گزینه یا مقداری پاک نمی‌شود. چیزی که هست (همان آدرس/کد) دوباره ساخته نمی‌شود.
 * - مشخصه‌هایی که به «دسته‌ی اصلی» وصل بودند ولی فقط به یک زیردسته می‌خورند (مثلاً RAM روی همه‌ی
 *   «لپتاپ و کمپیوتر»، که ماوس را هم مجبور به RAM می‌کرد) به زیردسته‌ی درست برده می‌شوند (MOVES).
 * - نوع چند مشخصه‌ی متنی که در اصل لیست انتخابی‌اند (فصل، نوع اتصال، …) فقط اگر هیچ جنسی مقدار
 *   نداشته باشد «لیست انتخابی» می‌شود (TYPE_CHANGES).
 * - چند بار اجرا کردنش بی‌خطر است.
 */
class CatalogTaxonomySeeder extends Seeder
{
    /** آدرس دسته‌ی بالادست ← [[نام، آدرس]] (زیر همان دسته، بعد از زیردسته‌های موجود) */
    public const SUBCATEGORIES = [
        // ───── موبایل و لوازم
        'mobile-phones' => [
            ['موبایل تکنو', 'tecno-phones'],
            ['موبایل اینفینیکس', 'infinix-phones'],
            ['موبایل هواوی و آنر', 'huawei-honor-phones'],
            ['موبایل ویوو، اوپو و ریلمی', 'vivo-oppo-phones'],
        ],
        'mobile-accessories' => [
            ['پایه و هولدر موبایل', 'phone-holders'],
            ['مموری کارت', 'memory-cards'],
        ],
        // ───── لپتاپ و کمپیوتر
        'laptops' => [
            ['لپتاپ گیمینگ', 'gaming-laptops'],
        ],
        'computer-accessories' => [
            ['مانیتور', 'monitors'],
            ['کمپیوتر رومیزی (کیس)', 'desktop-pcs'],
            ['پرنتر و اسکنر', 'printers'],
            ['روتر و مودم انترنت', 'routers'],
            ['وبکم، مایک و هدست', 'webcams-headsets'],
        ],
        // ───── وسایل دیجیتال
        'smartwatches' => [
            ['ساعت هوشمند', 'smart-watches'],
            ['بند هوشمند', 'smart-bands'],
        ],
        'speakers' => [
            ['اسپیکر بلوتوث', 'bluetooth-speakers'],
            ['سیستم صوتی خانگی', 'home-audio'],
            ['مایک', 'microphones'],
        ],
        'cameras' => [
            ['کمره‌ی عکاسی', 'photo-cameras'],
            ['کمره‌ی مداربسته', 'cctv-cameras'],
        ],
        'gaming' => [
            ['کنسول بازی', 'game-consoles'],
            ['دسته و لوازم بازی', 'game-accessories'],
        ],
        // ───── بوت و کفش
        'mens-shoes' => [
            ['چپلک و دمپایی مردانه', 'mens-slippers'],
        ],
        'womens-shoes' => [
            ['بوت پاشنه‌بلند', 'womens-heels'],
            ['بوت تخت زنانه', 'womens-flats'],
        ],
        'kids-shoes' => [
            ['بوت پسرانه', 'boys-shoes'],
            ['بوت دخترانه', 'girls-shoes'],
            ['بوت نوزاد', 'baby-shoes'],
        ],
        // ───── لباس
        'mens-clothing' => [
            ['دریشی مردانه', 'mens-suits'],
            ['زیرپوش و جوراب مردانه', 'mens-underwear'],
        ],
        'womens-clothing' => [
            ['پتلون و ساق زنانه', 'womens-pants'],
            ['لباس محفلی و عروسی', 'womens-party'],
            ['زیرپوش و جوراب زنانه', 'womens-underwear'],
        ],
        'kids-clothing' => [
            ['یونیفورم مکتب', 'school-uniforms'],
        ],
        'bags-accessories' => [
            ['زیورات بدلی', 'fashion-jewelry'],
        ],
        // ───── اجناس وطنی و محلی
        'handicrafts' => [
            ['ظروف مسی و سفالی', 'copper-pottery'],
            ['چوب‌کاری و منبت', 'woodwork'],
        ],
        'local-food' => [
            ['عسل', 'honey'],
            ['قروت و لبنیات خشک', 'qurut'],
            ['برنج و حبوبات وطنی', 'rice-pulses'],
        ],
        // ───── خانه و آشپزخانه
        'cookware' => [
            ['دیگ بخار (زودپز)', 'pressure-cookers'],
            ['ترموز و فلاسک', 'thermos'],
            ['قاشق، پنجه و کارد', 'cutlery'],
        ],
        'home-decor' => [
            ['قالین ماشینی و فرش', 'machine-carpets'],
            ['ساعت دیواری و آیینه', 'wall-clocks-mirrors'],
        ],
        'cleaning' => [
            ['مواد شوینده', 'detergents'],
            ['جارو و وسایل پاک‌کاری', 'brooms-mops'],
            ['وسایل تشناب', 'bathroom'],
        ],
        // ───── وسایل برقی خانه
        'kitchen-appliances' => [
            ['یخچال و فریزر', 'fridges'],
            ['اجاق گاز و داش', 'stoves'],
            ['مایکروویف', 'microwaves'],
            ['پلوپز و دیگ برقی', 'rice-cookers'],
        ],
        'heating-cooling' => [
            ['بخاری گازی، تیلی و چوبی', 'gas-heaters'],
            ['کولر و ایرکندیشن', 'air-coolers'],
        ],
        'irons' => [
            ['اتو', 'clothes-irons'],
            ['ماشین لباسشویی', 'washing-machines'],
            ['جاروبرقی', 'vacuum-cleaners'],
        ],
        'televisions' => [
            ['تلویزیون LED و هوشمند', 'led-tvs'],
            ['رسیور و دیش', 'satellite-receivers'],
        ],
        // زیرگروه تازه‌ی «برق و انرژی» (سولر، باتری، جنراتور) — در افغانستان بسیار پرفروش
        'home-appliances' => [
            ['برق و انرژی (سولر، باتری، جنراتور)', 'energy'],
        ],
        'energy' => [
            ['پنل سولر', 'solar-panels'],
            ['باتری و یو‌پی‌اس', 'batteries-ups'],
            ['استبلایزر', 'stabilizers'],
            ['جنراتور', 'generators'],
        ],
        // ───── آرایشی و صحی
        'makeup' => [
            ['لب (رژ و برق لب)', 'lips'],
            ['کرم پودر و پودر', 'face-makeup'],
            ['سرمه و آرایش چشم', 'eye-makeup'],
            ['لاک ناخن و حنا', 'nails-henna'],
        ],
        'personal-care' => [
            ['ماشین ریش و موزن', 'shavers'],
            ['مسواک و کریم دندان', 'oral-care'],
            ['شامپو و صابون', 'shampoo-soap'],
            ['کرم و لوسیون', 'creams'],
        ],
        'perfumes' => [
            ['عطر مردانه', 'mens-perfumes'],
            ['عطر زنانه', 'womens-perfumes'],
            ['عطر عربی و عود', 'arabic-perfumes'],
        ],
        // ───── کتاب، قرطاسیه و هنر
        'books' => [
            ['کتاب دانشگاهی', 'university-books'],
            ['کتاب آموزش زبان', 'language-books'],
        ],
        'stationery' => [
            ['ماشین حساب و وسایل هندسه', 'calculators'],
            ['وسایل دفتری', 'office-supplies'],
        ],
        // ───── لوازم ورزشی
        'sportswear' => [
            ['لباس فوتبال', 'football-kits'],
            ['لباس ورزشی مردانه', 'mens-sportswear'],
            ['لباس ورزشی زنانه', 'womens-sportswear'],
        ],
        'balls-games' => [
            ['توپ فوتبال', 'footballs'],
            ['والیبال و باسکتبال', 'volleyball-basketball'],
            ['کرکت', 'cricket'],
            ['شطرنج و بازی فکری', 'board-games'],
        ],
        'fitness' => [
            ['دمبل و وزنه', 'dumbbells'],
            ['وسایل بوکس و رزمی', 'martial-arts'],
            ['تردمیل و دستگاه ورزشی', 'gym-machines'],
        ],
        'bicycles' => [
            ['بایسکل بزرگسال', 'adult-bicycles'],
            ['بایسکل بچه‌گانه', 'kids-bicycles'],
        ],
        'outdoor' => [
            ['خیمه و کیسه‌ی خواب', 'tents'],
            ['وسایل پکنیک', 'picnic'],
        ],
    ];

    /**
     * مشخصه‌ها: کد ← [نام، نوع، واحد، نوع‌ساز، فیلتر، گزینه‌ها [[مقدار، آدرس]]].
     * برای مشخصه‌ی موجود فقط گزینه‌های نبوده اضافه می‌شود (نام و تنظیم صاحب پروژه دست نمی‌خورد).
     */
    public const ATTRIBUTES = [
        // ── گزینه‌های بیشتر برای مشخصه‌های موجود
        'shoe_size' => ['سایز کفش', 'select', null, true, true, [['۳۵', '35'], ['۴۷', '47']]],
        'storage' => ['حافظه‌ی داخلی', 'select', 'GB', true, true, [['۸', '8'], ['۲۰۴۸ (۲ ترابایت)', '2048']]],
        'ram' => ['RAM', 'select', 'GB', false, true, [['۱', '1'], ['۶۴', '64']]],
        'material' => ['جنس', 'select', null, false, true, [
            ['سیلیکون', 'silicone'], ['فلزی', 'metal'], ['فولادی (استیل)', 'steel'], ['چوبی', 'wood'],
            ['شیشه‌ای', 'glass'], ['چینی و سرامیک', 'ceramic'], ['مسی', 'copper'], ['سنگی', 'stone'],
            ['نقره', 'silver-metal'], ['بدلی', 'imitation'], ['المونیم', 'aluminium'],
        ]],
        // ── مشخصه‌هایی که صاحب پروژه ساخته بود و حالا لیست انتخابی می‌شوند (TYPE_CHANGES)
        'connection_type' => ['نوع اتصال', 'select', null, false, true, [
            ['Type-C', 'type-c'], ['Lightning (آیفون)', 'lightning'], ['Micro USB', 'micro-usb'], ['USB', 'usb-a'],
            ['بلوتوث', 'bluetooth'], ['Wi-Fi', 'wifi'], ['بی‌سیم (دانگل)', 'wireless-dongle'],
            ['جک ۳٫۵ (AUX)', 'aux'], ['HDMI', 'hdmi'], ['VGA', 'vga'],
        ]],
        'shoe_type' => ['نوع کفش', 'select', null, false, true, [
            ['روزمره', 'casual'], ['رسمی', 'formal'], ['ورزشی (کتانی)', 'sport'], ['ساق‌دار (موزه)', 'boot'],
            ['چپلی', 'chapli'], ['سندل', 'sandal'], ['دمپایی', 'slipper'], ['کوه‌نوردی', 'hiking'],
        ]],
        'season' => ['فصل استفاده', 'select', null, false, true, [
            ['تابستانی', 'summer'], ['زمستانی', 'winter'], ['بهاری و خزانی', 'spring-autumn'], ['چهار فصل', 'all-seasons'],
        ]],
        'material_type' => ['جنس پارچه', 'select', null, false, true, [
            ['نخی (پنبه)', 'cotton'], ['کتان', 'linen'], ['پشمی', 'wool'], ['ابریشمی', 'silk'], ['پلی‌استر', 'polyester'],
            ['تترون', 'tetron'], ['ویسکوز', 'viscose'], ['جین (کاوبای)', 'denim'], ['مخمل', 'velvet'],
            ['شیفون', 'chiffon'], ['کرپ', 'crepe'], ['ساتن', 'satin'],
        ]],
        'sewing_type' => ['نوع گلدوزی', 'select', null, false, true, [
            ['دست‌دوز', 'hand'], ['ماشین‌دوز', 'machine'], ['خامک‌دوزی', 'khamak'], ['سوزن‌دوزی', 'needle'],
            ['مهره و پولک‌دوزی', 'beads'], ['گلدوزی هزارگی', 'hazaragi'], ['بی گلدوزی', 'none'],
        ]],
        'made_in' => ['کشور سازنده', 'select', null, false, true, [
            ['افغانستان', 'afghanistan'], ['چین', 'china'], ['ایران', 'iran'], ['پاکستان', 'pakistan'], ['ترکیه', 'turkey'],
            ['هند', 'india'], ['امارات', 'uae'], ['ازبکستان', 'uzbekistan'], ['ویتنام', 'vietnam'], ['کوریا', 'korea'],
            ['جاپان', 'japan'], ['آلمان', 'germany'], ['امریکا', 'usa'], ['تایلند', 'thailand'], ['مالیزیا', 'malaysia'],
        ]],
        // ── مشخصه‌هایی که صاحب پروژه در پنل ساخت (روی نصب تازه هم ساخته شوند؛ موجود دست نمی‌خورد)
        'processor' => ['پروسسور', 'text', null, false, false, []],
        'battery_capacity' => ['ظرفیت باتری', 'number', 'mAh', false, false, []],
        'count' => ['تعداد به دانه', 'number', null, false, false, []],
        'main_camera' => ['دوربین اصلی', 'number', 'mPixel', false, false, []],
        'charger_power' => ['توان شارژ', 'number', 'W', false, false, []],
        'cable_lenght' => ['طول کیبل', 'number', 'cm', false, false, []],
        'weight' => ['وزن', 'number', 'Kg', false, false, []],
        'product_date' => ['تاریخ تولید', 'text', null, false, false, []],
        'expir_date' => ['تاریخ انقضا', 'text', null, false, false, []],
        'hand_caraft' => ['دست‌ساز', 'boolean', null, false, false, []],
        'author' => ['نویسنده', 'text', null, false, false, []],
        'publisher' => ['ناشر', 'text', null, false, false, []],
        'page_count' => ['تعداد صفحه', 'number', null, false, false, []],
        'publish_year' => ['سال چاپ', 'text', null, false, false, []],
        // ── مشخصه‌های تازه
        'sim_count' => ['تعداد سیم‌کارت', 'select', null, false, true, [['یک سیم', 'single'], ['دو سیم', 'dual'], ['سیم و ای‌سیم', 'esim']]],
        'network' => ['شبکه', 'select', null, false, true, [['2G', '2g'], ['3G', '3g'], ['4G', '4g'], ['5G', '5g']]],
        'os' => ['سیستم عامل', 'select', null, false, true, [
            ['اندروید', 'android'], ['iOS (آیفون)', 'ios'], ['HarmonyOS (هواوی)', 'harmony'], ['ویندوز', 'windows'],
            ['macOS (مک)', 'macos'], ['بی سیستم عامل', 'none'],
        ]],
        'front_camera' => ['دوربین سلفی', 'number', 'mPixel', false, false, []],
        'storage_type' => ['نوع حافظه', 'select', null, false, true, [['SSD', 'ssd'], ['HDD', 'hdd'], ['SSD و HDD', 'ssd-hdd'], ['eMMC', 'emmc']]],
        'keyboard_language' => ['حروف کیبورد', 'select', null, false, true, [
            ['انگلیسی', 'en'], ['انگلیسی و دری', 'en-fa'], ['انگلیسی و پشتو', 'en-ps'], ['انگلیسی و عربی', 'en-ar'],
        ]],
        'resolution' => ['کیفیت تصویر', 'select', null, false, true, [
            ['HD (720p)', 'hd'], ['Full HD (1080p)', 'full-hd'], ['2K', '2k'], ['4K (UHD)', '4k'], ['8K', '8k'],
        ]],
        'compatible_model' => ['مناسب برای مودل', 'text', null, false, false, []],
        'glass_type' => ['نوع گلس', 'select', null, false, true, [
            ['گلس ساده', 'normal'], ['گلس تمام صفحه', 'full'], ['گلس سرامیکی', 'ceramic'],
            ['گلس حریم خصوصی (پرایوسی)', 'privacy'], ['گلس مات', 'matte'],
        ]],
        'headphone_type' => ['نوع گوشکی', 'select', null, false, true, [
            ['ایربادز (داخل گوش)', 'earbuds'], ['هدفون سری', 'over-ear'], ['گوشکی سیمی', 'wired'], ['هندزفری گردنی', 'neckband'],
        ]],
        'fast_charge' => ['شارژ سریع', 'boolean', null, false, true, []],
        'holder_type' => ['نوع پایه', 'select', null, false, true, [
            ['پایه‌ی موتر', 'car'], ['پایه‌ی رومیزی', 'desk'], ['سلفی‌استیک', 'selfie-stick'], ['سه‌پایه', 'tripod'],
        ]],
        'card_type' => ['نوع کارت', 'select', null, false, true, [['Micro SD', 'micro-sd'], ['SD', 'sd']]],
        'printer_type' => ['نوع پرنتر', 'select', null, false, true, [
            ['لیزری', 'laser'], ['جوهری (رنگه)', 'inkjet'], ['چندکاره (پرنت، اسکن، کاپی)', 'multifunction'], ['حرارتی (رسید و بل)', 'thermal'],
        ]],
        'internet_type' => ['نوع انترنت', 'select', null, false, true, [
            ['Wi-Fi', 'wifi'], ['سیم‌کارتی (4G)', 'sim-4g'], ['سیم‌کارتی (5G)', 'sim-5g'], ['فایبر و کیبلی', 'fiber'],
        ]],
        'compatible_with' => ['سازگار با', 'select', null, false, true, [
            ['اندروید و آیفون', 'both'], ['فقط اندروید', 'android'], ['فقط آیفون', 'ios'],
        ]],
        'water_resistant' => ['ضد آب', 'boolean', null, false, true, []],
        'power' => ['توان برق', 'number', 'W', false, false, []],
        'camera_type' => ['نوع کمره', 'select', null, false, true, [
            ['دیجیتال کوچک', 'compact'], ['حرفه‌ای (DSLR و بی‌آینه)', 'pro'], ['کمره‌ی ورزشی', 'action'], ['کمره‌ی فوری', 'instant'],
        ]],
        'night_vision' => ['دید در شب', 'boolean', null, false, true, []],
        'platform' => ['نوع کنسول', 'select', null, false, true, [
            ['پلی‌استیشن', 'playstation'], ['ایکس‌باکس', 'xbox'], ['نینتندو', 'nintendo'], ['کمپیوتر (PC)', 'pc'], ['موبایل', 'mobile'],
        ]],
        'smart_tv' => ['هوشمند (انترنتی)', 'boolean', null, false, true, []],
        'voltage' => ['ولتاژ', 'select', null, false, true, [
            ['۲۲۰ ولت (برق شهری)', '220v'], ['۱۲ ولت (باتری و سولر)', '12v'], ['۲۲۰ و ۱۲ ولت', '220v-12v'],
        ]],
        'capacity_liter' => ['گنجایش', 'number', 'لیتر', false, true, []],
        'capacity_kg' => ['گنجایش لباس', 'number', 'کیلو', false, true, []],
        'fuel_type' => ['نوع سوخت', 'select', null, false, true, [
            ['برق', 'electric'], ['گاز', 'gas'], ['تیل', 'oil'], ['چوب و ذغال', 'wood-coal'], ['سولر', 'solar'],
        ]],
        'fan_type' => ['نوع پکه', 'select', null, false, true, [
            ['پکه‌ی سقفی', 'ceiling'], ['پکه‌ی ایستاده', 'stand'], ['پکه‌ی رومیزی', 'table'], ['پکه‌ی دیواری', 'wall'], ['پکه‌ی چارجی', 'rechargeable'],
        ]],
        'burners' => ['تعداد شعله', 'number', null, false, true, []],
        'washer_type' => ['نوع لباسشویی', 'select', null, false, true, [['اتومات', 'automatic'], ['نیمه‌اتومات (دو حوضه)', 'twin-tub']]],
        'battery_ah' => ['ظرفیت باتری (آمپر ساعت)', 'number', 'Ah', false, true, []],
        'battery_type' => ['نوع باتری', 'select', null, false, true, [
            ['خشک (جل)', 'gel'], ['لیتیم', 'lithium'], ['آبی (اسیدی)', 'acid'],
        ]],
        'power_source' => ['نوع برق دستگاه', 'select', null, false, true, [
            ['برقی (سیمی)', 'corded'], ['چارجی', 'rechargeable'], ['باتری قلمی', 'battery'],
        ]],
        'kids_shoe_size' => ['سایز بوت بچه‌گانه', 'select', null, true, true, [
            ['۱۸', '18'], ['۱۹', '19'], ['۲۰', '20'], ['۲۱', '21'], ['۲۲', '22'], ['۲۳', '23'], ['۲۴', '24'], ['۲۵', '25'], ['۲۶', '26'],
            ['۲۷', '27'], ['۲۸', '28'], ['۲۹', '29'], ['۳۰', '30'], ['۳۱', '31'], ['۳۲', '32'], ['۳۳', '33'], ['۳۴', '34'], ['۳۵', '35'],
        ]],
        'sole_material' => ['جنس زیره', 'select', null, false, true, [
            ['رابر', 'rubber'], ['پی‌وی‌سی (PVC)', 'pvc'], ['چرم', 'leather'], ['فوم (EVA)', 'eva'],
        ]],
        'watch_type' => ['نوع ساعت', 'select', null, false, true, [
            ['عقربه‌ای', 'analog'], ['دیجیتال', 'digital'], ['عقربه‌ای و دیجیتال', 'both'],
        ]],
        'uv_protection' => ['محافظ آفتاب (UV)', 'boolean', null, false, true, []],
        'dimensions' => ['اندازه', 'text', null, false, false, []],
        'carpet_type' => ['نوع بافت', 'select', null, false, true, [['دست‌باف', 'handmade'], ['ماشینی', 'machine']]],
        'origin_province' => ['ولایت تولید', 'text', null, false, true, []],
        'pack_size' => ['وزن یا حجم بسته', 'select', null, true, true, [
            ['۱۰۰ گرم', '100g'], ['۲۵۰ گرم', '250g'], ['۵۰۰ گرم', '500g'], ['۱ کیلو', '1kg'], ['۲ کیلو', '2kg'], ['۵ کیلو', '5kg'],
            ['۲۵۰ ملی‌لیتر', '250ml'], ['۵۰۰ ملی‌لیتر', '500ml'], ['۱ لیتر', '1l'], ['۲ لیتر', '2l'], ['۵ لیتر', '5l'],
        ]],
        'bed_size' => ['اندازه‌ی بستر', 'select', null, false, true, [['یک نفره', 'single'], ['دو نفره', 'double'], ['بچه‌گانه', 'kids']]],
        'perfume_volume' => ['حجم عطر', 'select', 'ml', true, true, [
            ['۳', '3'], ['۶', '6'], ['۱۲', '12'], ['۳۰', '30'], ['۵۰', '50'], ['۷۵', '75'], ['۱۰۰', '100'], ['۲۰۰', '200'],
        ]],
        'perfume_type' => ['نوع عطر', 'select', null, false, true, [
            ['ادو پرفیوم (EDP)', 'edp'], ['ادو تویلت (EDT)', 'edt'], ['عطر روغنی', 'oil'], ['بادی اسپری', 'body-spray'],
        ]],
        'skin_type' => ['مناسب برای پوست', 'select', null, false, true, [
            ['همه نوع پوست', 'all'], ['خشک', 'dry'], ['چرب', 'oily'], ['حساس', 'sensitive'], ['مختلط', 'combination'],
        ]],
        'language' => ['زبان', 'select', null, false, true, [
            ['دری', 'dari'], ['پشتو', 'pashto'], ['انگلیسی', 'english'], ['عربی', 'arabic'], ['دو زبانه', 'bilingual'],
        ]],
        'cover_type' => ['نوع جلد', 'select', null, false, true, [['جلد نرم', 'paperback'], ['جلد سخت', 'hardcover']]],
        'grade' => ['صنف', 'select', null, false, true, [
            ['صنف ۱', 'g1'], ['صنف ۲', 'g2'], ['صنف ۳', 'g3'], ['صنف ۴', 'g4'], ['صنف ۵', 'g5'], ['صنف ۶', 'g6'],
            ['صنف ۷', 'g7'], ['صنف ۸', 'g8'], ['صنف ۹', 'g9'], ['صنف ۱۰', 'g10'], ['صنف ۱۱', 'g11'], ['صنف ۱۲', 'g12'],
            ['کانکور', 'kankor'],
        ]],
        'paper_type' => ['نوع کاغذ', 'select', null, false, true, [['خط‌دار', 'lined'], ['ساده', 'plain'], ['شطرنجی', 'grid']]],
        'pen_type' => ['نوع قلم', 'select', null, false, true, [
            ['قلم خودکار', 'ballpoint'], ['پنسل', 'pencil'], ['ماژیک', 'marker'], ['قلم رنگه', 'colored'], ['قلم فیلتر', 'felt'],
        ]],
        'ball_size' => ['سایز توپ', 'select', null, false, true, [['۳', '3'], ['۴', '4'], ['۵', '5'], ['۶', '6'], ['۷', '7']]],
        'wheel_size' => ['اندازه‌ی تایر', 'select', 'اینچ', true, true, [
            ['۱۲', '12'], ['۱۶', '16'], ['۲۰', '20'], ['۲۴', '24'], ['۲۶', '26'], ['۲۷٫۵', '27-5'], ['۲۹', '29'],
        ]],
        'gears' => ['تعداد دنده', 'number', null, false, false, []],
        'person_capacity' => ['گنجایش (نفر)', 'number', 'نفر', false, true, []],
    ];

    /** مشخصه‌ی متنی/عددی که به این نوع عوض می‌شود — فقط اگر هیچ جنسی مقدار نداشته باشد. */
    public const TYPE_CHANGES = [
        'connection_type' => 'select', 'shoe_type' => 'select', 'season' => 'select', 'material_type' => 'select',
        'sewing_type' => 'select', 'made_in' => 'select',
        // پروسسور «عدد GHz» بود ولی مقدارش متن است (مثلاً «Core i7 نسل ۸») — مقدار در value_text است
        'processor' => 'text',
    ];

    /** مشخصه‌هایی که از دسته‌ی اصلی برداشته و به زیردسته‌ی درست داده می‌شوند: آدرس ← [کدها] */
    public const MOVES = [
        'mobile' => ['storage', 'model', 'ram', 'battery_capacity', 'color', 'screen_size', 'condition'],
        'computers' => ['storage', 'ram', 'graphics', 'battery_capacity', 'processor', 'model', 'color', 'screen_size'],
        'digital' => ['screen_size'],
        'local-products' => ['weight', 'material'],
        'books-art' => ['publisher'],
        // سایز لباس و کفش روی دسته‌ی اصلی بود و به بکس، ساعت و بوت بچه‌گانه هم می‌رسید
        'clothing' => ['clothing_size'],
        'shoes' => ['shoe_size'],
        'kids-shoes' => ['kids_size'],
    ];

    /** آدرس دسته ← [[کد، لازم؟]] (زیردسته‌ها به ارث می‌برند) */
    public const CATEGORY_ATTRIBUTES = [
        // موبایل و لوازم
        'mobile-phones' => [['model', true], ['storage', true], ['ram', false], ['color', true], ['condition', true], ['warranty', false],
            ['screen_size', false], ['battery_capacity', false], ['main_camera', false], ['front_camera', false],
            ['sim_count', false], ['network', false], ['os', false]],
        'mobile-accessories' => [['model', false], ['condition', false]],
        'chargers' => [['charger_power', false], ['connection_type', false], ['fast_charge', false]],
        'cables' => [['connection_type', false], ['cable_lenght', false], ['fast_charge', false]],
        'phone-cases' => [['compatible_model', false], ['material', false]],
        'screen-protectors' => [['compatible_model', false], ['glass_type', false]],
        'power-banks' => [['battery_capacity', false], ['charger_power', false], ['connection_type', false], ['fast_charge', false]],
        'headphones' => [['headphone_type', false], ['connection_type', false], ['compatible_with', false]],
        'phone-holders' => [['holder_type', false]],
        'memory-cards' => [['storage', false], ['card_type', false]],
        // لپتاپ و کمپیوتر
        'laptops' => [['color', false], ['battery_capacity', false], ['storage_type', false], ['os', false], ['keyboard_language', false]],
        'computer-accessories' => [['model', false], ['color', false], ['condition', false]],
        'mouse-keyboard' => [['connection_type', false], ['keyboard_language', false]],
        'storage-drives' => [['storage', false], ['storage_type', false], ['connection_type', false]],
        'laptop-bags' => [['screen_size', false], ['material', false]],
        'laptop-chargers' => [['charger_power', false], ['compatible_model', false]],
        'monitors' => [['screen_size', false], ['resolution', false], ['connection_type', false]],
        'desktop-pcs' => [['processor', false], ['ram', false], ['storage', false], ['storage_type', false], ['graphics', false], ['os', false]],
        'printers' => [['printer_type', false], ['connection_type', false]],
        'routers' => [['internet_type', false]],
        'webcams-headsets' => [['connection_type', false], ['resolution', false]],
        // وسایل دیجیتال
        'tablets' => [['screen_size', false], ['battery_capacity', false], ['main_camera', false], ['network', false], ['os', false]],
        'smartwatches' => [['compatible_with', false], ['water_resistant', false], ['screen_size', false]],
        'speakers' => [['connection_type', false], ['power', false], ['water_resistant', false]],
        'cameras' => [['camera_type', false], ['main_camera', false]],
        'cctv-cameras' => [['resolution', false], ['night_vision', false], ['connection_type', false]],
        'gaming' => [['platform', false]],
        'game-consoles' => [['storage', false]],
        // بوت و کفش
        'shoes' => [['season', false], ['sole_material', false], ['made_in', false]],
        'mens-shoes' => [['shoe_size', true]],
        'womens-shoes' => [['shoe_size', true]],
        'kids-shoes' => [['kids_shoe_size', true]],
        // لباس
        'clothing' => [['material_type', false], ['season', false], ['made_in', false]],
        'mens-clothing' => [['clothing_size', true]],
        'womens-clothing' => [['clothing_size', true]],
        'mens-hazaragi' => [['sewing_type', false], ['hand_caraft', false]],
        'perahan-tunban' => [['sewing_type', false]],
        'waskat' => [['sewing_type', false]],
        'chapan' => [['sewing_type', false], ['hand_caraft', false]],
        'womens-traditional' => [['sewing_type', false], ['hand_caraft', false]],
        'womens-afghan' => [['sewing_type', false], ['hand_caraft', false]],
        'womens-party' => [['sewing_type', false]],
        'wrist-watches' => [['watch_type', false], ['water_resistant', false]],
        'sunglasses' => [['uv_protection', false]],
        // اجناس وطنی و محلی
        'local-products' => [['origin_province', false]],
        'handicrafts' => [['material', false], ['hand_caraft', false], ['dimensions', false]],
        'carpets-rugs' => [['carpet_type', false]],
        'embroidery' => [['sewing_type', false]],
        'local-food' => [['weight', false], ['pack_size', false], ['product_date', false], ['expir_date', false]],
        // خانه و آشپزخانه
        'cookware' => [['material', false], ['color', false], ['made_in', false]],
        'pots-pans' => [['capacity_liter', false]],
        'pressure-cookers' => [['capacity_liter', false]],
        'thermos' => [['capacity_liter', false]],
        'tea-sets' => [['count', false]],
        'cutlery' => [['count', false]],
        'storage-containers' => [['count', false], ['capacity_liter', false]],
        'home-decor' => [['color', false], ['material', false], ['dimensions', false]],
        'machine-carpets' => [['carpet_type', false]],
        'bedding' => [['bed_size', false]],
        'cleaning' => [['pack_size', false], ['made_in', false]],
        // وسایل برقی خانه
        'home-appliances' => [['model', false], ['warranty', false], ['condition', false], ['power', false], ['voltage', false], ['color', false]],
        'kettles' => [['capacity_liter', false]],
        'fridges' => [['capacity_liter', false]],
        'stoves' => [['fuel_type', false], ['burners', false]],
        'microwaves' => [['capacity_liter', false]],
        'rice-cookers' => [['capacity_liter', false]],
        'heating-cooling' => [['fuel_type', false]],
        'fans' => [['fan_type', false]],
        'washing-machines' => [['capacity_kg', false], ['washer_type', false]],
        'televisions' => [['screen_size', false], ['resolution', false], ['smart_tv', false]],
        'batteries-ups' => [['battery_ah', false], ['battery_type', false]],
        'generators' => [['fuel_type', false]],
        // آرایشی و صحی
        'makeup' => [['color', false], ['skin_type', false]],
        'creams' => [['skin_type', false], ['pack_size', false]],
        'shampoo-soap' => [['pack_size', false]],
        'shavers' => [['power_source', false], ['warranty', false]],
        'perfumes' => [['perfume_volume', false], ['perfume_type', false]],
        // کتاب، قرطاسیه و هنر
        'books' => [['author', false], ['publisher', false], ['page_count', false], ['publish_year', false], ['language', false], ['cover_type', false]],
        'textbooks' => [['grade', false]],
        'stationery' => [['count', false], ['color', false]],
        'notebooks' => [['page_count', false], ['paper_type', false]],
        'pens-pencils' => [['pen_type', false]],
        'school-bags' => [['material', false]],
        'art-supplies' => [['count', false], ['color', false]],
        'paintings' => [['dimensions', false], ['hand_caraft', false]],
        // لوازم ورزشی
        'sports' => [['color', false], ['made_in', false]],
        'sportswear' => [['clothing_size', true], ['material_type', false]],
        'balls-games' => [['ball_size', false]],
        'fitness' => [['weight', false]],
        'bicycles' => [['wheel_size', false], ['gears', false]],
        'tents' => [['person_capacity', false]],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->subcategories();
            $this->attributes();
            $this->moves();
            $this->attach();
        });
    }

    private function subcategories(): void
    {
        foreach (self::SUBCATEGORIES as $parentSlug => $items) {
            $parent = Category::query()->where('slug', $parentSlug)->first();
            if ($parent === null) {
                continue;   // دسته‌ی بالادست را صاحب پروژه پاک یا عوض کرده
            }
            $position = (int) Category::withTrashed()->where('parent_id', $parent->id)->max('position') + 1;
            foreach ($items as [$name, $slug]) {
                if (Category::withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }
                Category::create([
                    'parent_id' => $parent->id,
                    'slug' => $slug,
                    'name' => ['fa' => $name],
                    'position' => $position++,
                    // زیر دسته‌ی خاموش، خاموش ساخته شود
                    'is_active' => $parent->is_active,
                    'show_in_menu' => true,
                ]);
            }
        }
    }

    private function attributes(): void
    {
        $position = (int) Attribute::query()->max('position') + 1;
        foreach (self::ATTRIBUTES as $code => [$name, $type, $unit, $isVariant, $isFilterable, $options]) {
            $attribute = Attribute::query()->where('code', $code)->first();
            if ($attribute === null) {
                $attribute = Attribute::create([
                    'code' => $code,
                    'name' => ['fa' => $name],
                    'type' => $type,
                    'unit' => $unit,
                    'is_variant' => $isVariant,
                    'is_filterable' => $isFilterable,
                    'show_in_specs' => true,
                    'position' => $position++,
                    'is_active' => true,
                ]);
            } elseif (($to = self::TYPE_CHANGES[$code] ?? null) !== null && $attribute->type !== $to && ! $this->hasValues($attribute, $to)) {
                $attribute->forceFill(['type' => $to, 'is_filterable' => $isFilterable || $attribute->is_filterable])->save();
            }

            if (in_array($attribute->type, Attribute::OPTION_TYPES, true)) {
                $this->addOptions($attribute, $options);
            }
        }

        // تایپ نام مشخصه‌ی صاحب پروژه
        Attribute::query()->where('code', 'made_in')->get()
            ->filter(fn (Attribute $a): bool => $a->tr('name') === 'کشوره سازنده')
            ->each(fn (Attribute $a) => $a->forceFill(['name' => ['fa' => 'کشور سازنده']])->save());
        // «پروسسور» متنی واحد GHz نمی‌خواهد
        Attribute::query()->where('code', 'processor')->where('type', 'text')->where('unit', 'GHz')->update(['unit' => null]);
    }

    /** @param list<array{0: string, 1: string}> $options */
    private function addOptions(Attribute $attribute, array $options): void
    {
        $existing = $attribute->options()->pluck('slug')->all();
        $position = (int) $attribute->options()->max('position') + 1;
        foreach ($options as [$value, $slug]) {
            if (in_array($slug, $existing, true)) {
                continue;
            }
            AttributeOption::create([
                'attribute_id' => $attribute->id,
                'value' => ['fa' => $value],
                'slug' => $slug,
                'position' => $position++,
            ]);
            $existing[] = $slug;
        }
    }

    /** مقداری هست که با نوع تازه نخواند؟ (به «متنی» فقط مقدار عددی/گزینه‌ای مانع است، چون متن در value_text می‌ماند) */
    private function hasValues(Attribute $attribute, string $to): bool
    {
        $values = DB::table('product_attribute_values')->where('attribute_id', $attribute->id);
        if ($to === 'text') {
            $values->where(fn ($q) => $q->whereNotNull('value_number')->orWhereNotNull('attribute_option_id'));
        }

        return $values->exists() || DB::table('variant_attribute_values')->where('attribute_id', $attribute->id)->exists();
    }

    private function moves(): void
    {
        $ids = Attribute::query()->pluck('id', 'code');
        foreach (self::MOVES as $slug => $codes) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category === null) {
                continue;
            }
            DB::table('category_attribute')
                ->where('category_id', $category->id)
                ->whereIn('attribute_id', collect($codes)->map(fn (string $c) => $ids[$c] ?? 0)->all())
                ->delete();
        }
    }

    private function attach(): void
    {
        $ids = Attribute::query()->pluck('id', 'code');
        foreach (self::CATEGORY_ATTRIBUTES as $slug => $list) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category === null) {
                continue;
            }
            $existing = DB::table('category_attribute')->where('category_id', $category->id)->pluck('attribute_id')->map(fn ($id) => (int) $id)->all();
            $position = (int) DB::table('category_attribute')->where('category_id', $category->id)->max('position') + 1;
            foreach ($list as [$code, $required]) {
                $id = $ids[$code] ?? null;
                if ($id === null || in_array((int) $id, $existing, true)) {
                    continue;
                }
                DB::table('category_attribute')->insert([
                    'category_id' => $category->id,
                    'attribute_id' => $id,
                    'is_required' => $required,
                    'position' => $position++,
                ]);
                $existing[] = (int) $id;
            }
        }
    }
}
