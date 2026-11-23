# پیشرفت کار — بازارک (فروشگاه آنلاین برادران حکیمی)

> این فایل می‌گوید **چه شد و چرا**. قدم بعدی در `todo.md`. اگر جلسه‌ی تازه شروع شد، اول `CLAUDE.md`، بعد این فایل و `todo.md` را بخوان.
> آخرین به‌روزرسانی: ۲ قوس ۱۴۰۵ (۲۰۲۶-۱۱-۲۳)، پایان مرحله‌ی ۲-۲ (صفحه‌ی تسویه و سفارش)

## ۲ قوس ۱۴۰۵ (۲۰۲۶-۱۱-۲۳) — مرحله‌ی ۲-۲: صفحه‌ی تسویه، سفارش و پیگیری
- **ساخته شد:** `/checkout` (خریدار، گیرنده‌ی جدا با تیک «هدیه»، روش تحویل، آدرس، روز تحویل، روش پرداخت، یادداشت، خلاصه‌ی زنده)، `/order/{uuid}` (تشکر + وضعیت + اجناس + تحویل + پرداخت + جمع‌ها)، `/track` (پیگیری مهمان با شماره‌ی سفارش + موبایل)، `/account/orders` («سفارش‌های من») و سه سفارش آخر در «حساب من».
- فایل‌ها: `CheckoutController`، `OrderController`، `CheckoutRequest`، `app/Services/Orders/CheckoutPage.php`، `resources/views/storefront/{checkout/show,orders/{show,track,mine}}.blade.php`، متن‌های تازه در `lang/fa/checkout.php`، نام خانه‌ها در `lang/fa/validation.php`، دو محدودیت تازه در `AppServiceProvider` (`checkout`، `order-track`).
- **قاعده‌ی مهم:** هیچ محاسبه‌ی پولی در مرورگر نیست. `CheckoutPage` کرایه‌ی هر روزِ هر ولایتِ تحویل‌دار و جمعِ هر ترکیب (پرداخت × ولایت × روز، و حضوری) را در سرور فرمت می‌کند و Alpine فقط متن آماده را نشان می‌دهد؛ `CheckoutService` همه را زیر قفل از نو حساب می‌کند.
- **تصمیم فنی:** روزهای تحویلِ **همه‌ی** ولایت‌های تحویل‌دار یک‌جا در صفحه می‌آید و با عوض شدن ولایت فقط گروه خودش دیده می‌شود (رادیوهای پنهان `disabled` می‌شوند) — تا عوض کردن ولایت صفحه را دوباره بار نکند و نوشته‌های فورم از دست نرود.
- `collection_point_id` در فورم «اختیاری» است: اگر نیامد، سرویس اولین نمایندگی فعال را می‌گیرد (برای مرورگر بی جاوااسکریپت).
- فورم `novalidate` است (مثل بقیه‌ی فورم‌های سایت): خانه‌های پنهانِ «لازم» جلوی فرستادن فورم را نمی‌گیرند و همه‌ی اعتبارسنجی در سرور است.
- **آزمون در مرورگر واقعی** (Edge بی‌سر + CDP، درس بخش «مشخصات جنس»): روز دیرتر جمع را از ۱٬۶۵۰ به ۱٬۵۵۰ افغانی آورد؛ «حضوری» خودش روش پرداخت را `cash_point` کرد، آدرس را پنهان کرد و کرایه را «بی کرایه» نشان داد؛ تیک هدیه خانه‌های گیرنده را آورد؛ سفارش ثبت شد و صفحه‌ی تشکر با `HS-000002` آمد؛ بی خطای جاوااسکریپت. (آزمون روی دیتابیس `hakimi_store_test` با یک جنس آزمایشی انجام شد؛ دیتابیس کار دست نخورد.)
- `php artisan test` → **۳۴۲ تست سبز** (۱۹ تست تازه در `CheckoutPageTest`)؛ `vendor\bin\pint` مرتب؛ `npm run build` انجام شد.
- **قدم بعدی:** ۲-۳ سفارش‌ها در پنل مدیریت (لیست، صفحه‌ی سفارش، ماشین وضعیت، تاریخچه، برگه‌ی بسته‌بندی، دفتر موجودی، داشبورد).

## ۲ قوس ۱۴۰۵ (۲۰۲۶-۱۱-۲۳) — تأیید برنامه‌ی کلان و مرحله‌ی ۲-۱ (هسته‌ی سفارش)
- **صاحب پروژه ۱۱ تصمیم را تأیید کرد** («با پیشنهادهای شما موافقم») با سه تغییر: (۱) پیک هنگام تحویل جنس در دمِ در پول را می‌گیرد؛ (۲) پرداخت حضوری در **نمایندگی‌ها** هم هست (نه فقط دفتر مرکزی)؛ (۳) پرداخت آنلاین **فقط حساب‌پی** (بی حواله‌ی دستی، بی درگاه جدای بین‌المللی). توضیح صاحب پروژه: سایت حساب‌پی از ایران باز نمی‌شود؛ مردم از حساب بانکی افغانستان، Payoneer یا کارت‌های دیگر کیف‌پول حساب‌پی را پر می‌کنند و با آن آنلاین می‌پردازند (**تأییدنشده** تا متن رسمی حساب‌پی). اسناد (`PAYMENTS_AND_CHECKOUT`، `ASSESSMENT_AND_PLAN`، `DATABASE`) به‌روز شد.
- **ساخته شد (مرحله‌ی ۲-۱):** migration `2026_11_22_000100` (orders، seller_orders، order_items، order_status_history، stock_movements) و `2026_11_22_000200` (payment_methods، collection_points، payments)؛ هشت مدل؛ `CheckoutService`، `OrderStateMachine`، `StockLedger`؛ `PaymentMethodSeeder`؛ ۲۳ تست؛ روی دیتابیس کار migrate شد (فقط جدول تازه). نرخ کمیسیون هر خط هنگام ثبت سفارش کپی می‌شود؛ مثال صاحب پروژه (۶۸۰ / ۱۱٬۳۲۰) در تست است.
- **تصمیم فنی:** تا مرحله‌ی ۴، `CheckoutService` نرخ را از `CommissionService::rateFor` (ترتیب فعلی فروشنده ← دسته ← پیش‌فرض) می‌گیرد؛ مرحله‌ی ۴ ترتیب را به «جنس ← دسته ← فروشنده ← پیش‌فرض + استثنا» عوض می‌کند و همین‌جا اثر می‌گذارد. سفارش‌های ساخته‌شده تا آن وقت همان نرخ ثبت‌شده را نگه می‌دارند.
- `php artisan test` → ۳۲۳ تست سبز.

## ۱ قوس ۱۴۰۵ (۲۰۲۶-۱۱-۲۲) — «برنامه‌ی کلان» و بازنگری سفارش
- صاحب پروژه یک برنامه‌ی ۱۲ مرحله‌ای (سفارش و پرداخت چندفروشنده‌ای، پول نقد، حساب‌پی، خریدار خارج، کمیسیون، بازپرداخت، امنیت، AWS، بازاریابی) داد و خواست اول بررسی و اسناد باشد و **پیش از تغییر بزرگ دیتابیس تأیید گرفته شود**.
- **انجام شد (مرحله‌ی ۱، فقط اسناد؛ هیچ کد یا migration تازه‌ای نیست):** `docs/ASSESSMENT_AND_PLAN.md` (وضعیت، خطرها، معماری پیشنهادی، نقشه‌ی ۱۲ مرحله، ۱۱ تصمیم لازم)، `PAYMENTS_AND_CHECKOUT`، `COMMISSION_AND_SELLER_SETTLEMENT`، `SECURITY`، `API`، `GO_TO_MARKET_AND_MARKETING`، `DOMAIN_AND_HOSTING_RECOMMENDATION`؛ `aws-hosting.md` تکمیل شد (همان AWS_DEPLOYMENT)؛ `DATABASE.md` بخش چ-۲ (طرح v2)؛ `ARCHITECTURE.md` بخش ۷-۱۷؛ `ROADMAP.md` و `CLAUDE.md`.
- **پیام دوم صاحب پروژه (همان روز): «تخصیص سفارش چندفروشنده و محاسبه‌ی کمیسیون»** — وارد اسناد شد (`COMMISSION_AND_SELLER_SETTLEMENT.md` بازنویسی؛ `DATABASE.md`، `ARCHITECTURE.md`، `PAYMENTS_AND_CHECKOUT.md`). نکته‌ی تصمیم‌دار: ترتیب اولویت صاحب پروژه (جنس ← دسته ← فروشنده ← پیش‌فرض) با کد فعلی (فروشنده ← دسته) و با مزایای شروع (۰٪ اینستاگرامی) تداخل دارد؛ راه‌حل در طرح: «استثنا» (`is_exception`) بالاتر از همه.
- **پیش‌نویس ۴-۳ (سفارش تک‌فروشنده) کنار گذاشته شد** چون با «چندفروشنده + خریدار ≠ گیرنده + پرداخت جدا» نمی‌خواند؛ در `git stash` (`stash@{0}`) ماند، migrate نشد. دیتابیس کار و کد دست‌نخورده است.
- **بررسی کد:** SQL خام فقط ثابت؛ مدل‌ها همه `$fillable/$guarded`؛ `{!! !!}` فقط سه جا و امن؛ `composer audit` بی هشدار.
- **تأییدنشده‌ها:** مستندات حساب‌پی برای ابزار خودکار ۴۰۳ می‌دهد؛ کارت بین‌المللی برای پذیرنده‌ی ما معلوم نیست؛ Stripe/PayPal افغانستان را پشتیبانی نمی‌کنند (منابع ثانویه). **قیمت‌ها و دسترسی‌ها** (AWS، دامنه) تقریبی‌اند.
- دامنه (RDAP رسمی Verisign، ۱ قوس): `bazarak.com` ثبت است؛ `bazarakaf.com` رکورد ندارد (هنگام خرید باید دوباره دید).

## وضعیت حالا
- **ساخته‌شده تا حالا:** مرحله‌ی ۱ (اسناد)، ۲ (پایه)، ۲ب (بازارک: نام و لوگو، دسته‌ها، حساب مشتری، «در بازارک بفروشید»)، ۲ج (کمیسیون، حساب و تسویه‌ی فروشندگان، مزایا، راهنمای AWS)، ۲د (کلمه‌های افغانستانی، تحویل فقط کابل، موبایل و اپ وب)، ۳-۱ (برندها و مشخصه‌ها)، ۳-۲ (اجناس و نوع‌ها و عکس)، ۳-۳ (صفحه‌ی جنس، دسته با فیلتر، جستجو)، کرایه‌ی تحویل «روز + وزن»، ۴-۱ (سبد خرید)، تحویل حضوری بی کرایه، عکس AVIF، شماره‌ی تلفن مدیر در «حساب من»، اعلان‌های پنل (سبز ۸ ثانیه / سرخ با دلیل)، اصلاح «مشخصات جنس»، درج جنس پشت سر هم (ریدایرکت به فورم خالی، ماندن عکس بعد از خطا، عکس لیست)، هسته‌ی سفارش چندفروشنده (۲-۱)، **صفحه‌ی تسویه و سفارش و پیگیری و «سفارش‌های من» (۲-۲)**.
- **قدم بعدی:** مرحله‌ی ۲-۳ (سفارش‌ها در پنل مدیریت) ← مرحله‌ی ۳ (گرفتن پول نقد، رسید، نقش پیک) ← مرحله‌ی ۴ (کمیسیون و گزارش‌ها)؛ بعد ۳-۴ (بنر، sitemap). حساب‌پی (مرحله‌ی ۵) منتظر متن رسمی مستندات و حساب پذیرنده.
- `php artisan test` → ۳۲۳ تست سبز. `vendor\bin\pint` → مرتب.
- کد در GitHub: `https://github.com/4466127/Hakimi-online-store.git` (شاخه‌ی `main`).

## تصمیم‌های صاحب پروژه (۲۵ عقرب ۱۴۰۵)
1. **فروشگاه کاملاً مستقل از سیستم دکان (ERP).** هیچ اتصال، API همگام‌سازی، جدول `sync_*` یا ستون `erp_*`. فقط الگوبرداری. (مرحله‌ی ۶ دیگر «اتصال ERP» ندارد.)
2. **الگو: دیجی‌کالا**؛ هدف دور فروشگاهی در همان اندازه.
3. **پرداخت:** دو گزینه — آنلاین با حساب‌پی (HesabPay) و هنگام تحویل. حساب‌پی در مرحله‌ی ۵، فقط با مستندات رسمی و حساب پذیرنده‌ی صاحب پروژه.
4. **تحویل:** فعلاً فقط کابل (۲۶ عقرب: غزنی هم خاموش شد)؛ بقیه «زیر کار است».
5. **Git:** کامیت و پوش فقط به نام صاحب پروژه (`rezahakimi535`)؛ هیچ نشانی از Claude.
6. **نام: بازارک (Bazarak)** با لوگوی صاحب پروژه (اصل در `resources/brand/`، نسخه‌ی وب WebP در `public/images/brand/`). رنگ‌ها از لوگو.
7. **«در بازارک بفروشید»:** دیگران هم مثل دیجی‌کالا بفروشند. فعلاً فقط ثبت‌نام و بررسی فروشنده؛ به‌جای کد ملی **نمبر تذکره**. پنل فروشنده، کمیسیون و تسویه منتظر تصمیم صاحب پروژه.
8. **منوی دسته‌بندی کالاها** مثل دیجی‌کالا (سه سطح).
9. **عکس‌ها WebP** و کوچک (مثل دیجی‌کالا) برای انترنت ضعیف.
10. **کمیسیون (۲۶ عقرب):** موبایل ۳٪، لپتاپ و کمپیوتر ۴٪، لوازم جانبی ۶٪، کفش/لباس/کیف/سایر ۷٪؛ فقط روی فروش تحویل‌شده؛ ثبت‌نام، گذاشتن جنس و ماهانه رایگان؛ برگشت = برگشت کمیسیون؛ کرایه بیرون.
11. **تحویل:** اول خود صاحب پروژه؛ بعداً شرکت پیک؛ کرایه جدا از مشتری.
12. **میزبانی: AWS** (پیشنهاد Lightsail، `docs/aws-hosting.md`). کارت Visa موجود است. دامنه `.com`؛ `bazarak.com` گرفته شده (پیشنهاد `bazarakaf.com`).
13. **کلمه‌های افغانستانی** به‌جای ایرانی؛ «لباس هزارگی و افغانی»؛ سؤال «کد ملی» حذف.
14. **دسته‌ها:** «سلامت و پزشکی» برداشته؛ «کتاب، قرطاسیه و هنر» و «لوازم ورزشی» اضافه.
15. **اپ موبایل:** فعلاً اپ وب (PWA)؛ اپ واقعی بعد از MVP. ظاهر موبایل آرام، بی حرکت زیاد.

## آنچه در مرحله‌ی ۲ ساخته شد
| بخش | کجا |
|---|---|
| ورود مدیران با تلفن + قفل حساب | `app/Filament/Auth/Login.php`، شمارش در `app/Providers/AppServiceProvider.php` |
| تغییر اجباری رمز اولیه | `app/Http/Middleware/EnsurePasswordChanged.php`، `app/Filament/Auth/EditProfile.php`، منطق در `User::booted()` |
| نقش‌ها و دسترسی | `app/Enums/UserRole.php`، `app/Policies/*` |
| کاربران، صفحه‌ها، ولایت‌ها، تاریخچه | `app/Filament/Resources/*` |
| تنظیمات فروشگاه | `app/Filament/Pages/ManageStoreSettings.php`، `app/Services/Settings.php` (cache) |
| معلومات فروشگاه برای قالب‌ها | `app/Services/StoreInfo.php` |
| تاریخچه‌ی فعالیت | `app/Services/ActivityLogger.php`، trait `app/Models/Concerns/LogsActivity.php` |
| متن چندزبانه | trait `app/Models/Concerns/HasTranslations.php` (`$model->tr('title')`) |
| کمک‌کننده‌ها | `app/Support/{Jalali,Num,Phone,Money,Persian}.php` |
| عکس امن | `app/Services/ImageStore.php` (GD؛ در مرحله‌ی ۳ برای چند اندازه Intervention اضافه می‌شود) |
| قالب فروشگاه | `resources/views/storefront/*`، `resources/views/components/storefront/*`، `resources/css/app.css` |
| متن‌ها | `lang/fa/{admin,store,validation,date,num,auth,pagination,passwords}.php` |
| جدول‌ها | `database/migrations/0001_01_01_000000_create_users_table.php`، `2026_11_16_000100_create_foundation_tables.php` |

## نکته‌های فنی که باید بدانید
- **Filament 5** (نه ۳): فورم‌ها با `Filament\Schemas\Schema`؛ بخش‌ها `Filament\Schemas\Components\Section`؛ خانه‌های فورم `Filament\Forms\Components\*`؛ همه‌ی اکشن‌ها `Filament\Actions\*`؛ جدول: `->recordActions()` و `->toolbarActions()`. پیش از نوشتن کد تازه‌ی Filament، منبع بسته در `vendor/filament` را نگاه کن.
- هر منبع Filament در پوشه‌ی خودش: `Resources/Xs/{XResource.php, Schemas/XForm.php, Tables/XsTable.php, Pages/…}`.
- نام گروه منو از ترجمه می‌آید (`admin.nav.*`)؛ منبع باید `getNavigationGroup()` را بدهد.
- کلید ترجمه‌ای که خودش نقطه دارد (مثل `user.created` یا `store.name`) را نمی‌شود با `__('a.b.user.created')` خواند؛ کل لیست را بگیر و با کلید بخوان (نمونه: `ActivityLog::actionLabel()`).
- `Model::shouldBeStrict()` در محیط توسعه و تست روشن است: lazy loading و ستون ناموجود خطا می‌دهد (در جدول‌ها `->with()` بگذار).
- آدرس دیسک `public` نسبی است (`/storage`) تا با IP شبکه‌ی خانه هم کار کند.
- چیزهای cacheشده: تنظیمات (`settings.all`)، پول پیش‌فرض، نام ولایت‌های تحویل (`provinces.delivery`)، صفحه‌های پاورقی (`pages.footer`)؛ هر کدام با ذخیره‌ی مدل خودش پاک می‌شود.
- سربرگ و نوار پایین، بخش‌هایی را که آدرس‌شان هنوز نیست (جستجو `search`، سبد `cart.show`، حساب `account`، دسته‌ها `categories`) خودکار غیرفعال نشان می‌دهند؛ وقتی route با همین نام ساخته شد خودشان فعال می‌شوند.
- فایل‌های Filament در `public/css|js|fonts/filament` در git نیست؛ `composer install` خودش می‌سازد.
- **محیط:** اگر MySQL خاموش بود، XAMPP Control Panel → Start. `npm` در ترمینالی که پیش از نصب Node باز بوده شناخته نمی‌شود؛ VS Code را ببند و باز کن.
- کاربر صاحبِ محلی با شماره‌ی موقت `0700000000` ساخته شده؛ رمز اولیه در گفتگو به صاحب پروژه داده شد و در اولین ورود عوض می‌شود.

## آنچه در بخش ۲ب ساخته شد
| بخش | کجا |
|---|---|
| لوگو و رنگ | `public/images/brand/*.webp`، `public/favicon.{ico,png}`، `public/apple-touch-icon.png`؛ `StoreInfo::logoUrl()/logoDarkUrl()/iconUrl()`؛ `@theme` در `resources/css/app.css`؛ `brandLogo` در `AdminPanelProvider` |
| دسته‌ها | `app/Models/Category.php` (عمق و مسیر خودکار، حداکثر ۳ سطح، جلوگیری از حلقه)؛ `app/Services/CategoryMenu.php` (درخت cacheشده: `categories.menu`)؛ `app/Filament/Resources/Categories/*`؛ `database/seeders/CategorySeeder.php` |
| منوی بزرگ و صفحه‌های دسته | `resources/views/components/storefront/mega-menu.blade.php`، `resources/views/storefront/categories/*`، `CategoryController` |
| حساب مشتری | guard `customer` در `config/auth.php`؛ `app/Models/Customer.php`؛ `CustomerAuthController`، `AccountController`؛ `app/Http/Requests/Storefront/Customer*Request.php`؛ `resources/views/storefront/{auth,account}/*`؛ `CustomerResource` |
| فروشندگان | `app/Models/Seller.php`، `app/Enums/Seller{Status,Type}.php`، `app/Services/SellerOnboarding.php`، `SellerController`، `SellerOnboardingController`، `app/Http/Requests/Storefront/Seller*Request.php`، `resources/views/storefront/seller/*`، `app/Filament/Resources/Sellers/*` |
| خانه‌های فورم فروشگاه | `resources/views/components/storefront/{field,form-errors,flash}.blade.php`؛ کلاس‌های `.form-*` و `.alert-*` در `app.css` |
| متن‌ها | `lang/fa/customer.php`، `lang/fa/seller.php`، بخش‌های تازه در `store.php` و `admin.php` |

## آنچه در بخش ۲ج ساخته شد
| بخش | کجا |
|---|---|
| موتور کمیسیون | `app/Services/Commission/{CommissionService,SaleLine,AppliedRate,SellerIncentives}.php` |
| جدول‌ها | `database/migrations/2026_11_17_000100_create_commission_tables.php` (نرخ‌های پیش‌فرض روی دسته‌های موجود هم همین‌جا) |
| مدل‌ها | `CommissionEntry`، `SellerTransaction` (هر دو تغییرناپذیر)، `SellerSettlement`، `SellerCommissionRate` |
| پنل | `Filament/Pages/ManageMarketplaceSettings.php`، `Resources/CommissionEntries/*`، `Resources/Settlements/*`، `Resources/Sellers/RelationManagers/*`، دکمه‌های تسویه و اصلاح در `ViewSeller`، `Widgets/MarketplaceFinanceStats.php` |
| سایت | `storefront/seller/commissions.blade.php`، بخش‌های تازه در `landing` و `status` |
| متن‌ها | `lang/fa/commission.php`، بخش‌های `marketplace_settings` و `finance` در `admin.php` |
| تست | `tests/Feature/CommissionTest.php` |

## آنچه در بخش ۲د ساخته شد (۲۶ عقرب ۱۴۰۵)
- migration `2026_11_18_000100_afghan_wording_and_kabul_only_delivery`: نام‌های ایرانی دسته‌ها ← افغانستانی (فقط اگر مدیر نام را خودش عوض نکرده باشد)، ترتیب تازه (لباس زنانه اول)، «سلامت و پزشکی» به سطل زباله، صفحه‌ی «مرجوعی» ← «برگشت جنس و ضمانت»، غزنی بی تحویل. برگشت‌پذیر (`down`).
- `CategorySeeder`: درخت تازه (۱۱ دسته‌ی اصلی) با «کتاب، قرطاسیه و هنر» و «لوازم ورزشی». روی لپتاپ بعد از migrate یک بار `php artisan db:seed --class=CategorySeeder` اجرا شد تا دسته‌های تازه ساخته شود.
- متن‌ها (`lang/fa`): کالا ← جنس/اجناس، مرجوعی ← برگشت جنس؛ جستجو «جستجو در بازارک».
- موبایل: سربرگ یک‌خطی؛ دایره‌های دسته در صفحه‌ی اصلی؛ `manifest.webmanifest` و آیکون‌های ۱۹۲/۵۱۲.
- بررسی دامنه با RDAP رسمی Verisign: `bazarak.com` از ۲۰۲۰ ثبت است (تا اپریل ۲۰۲۷)؛ نام‌های آزاد در `docs/aws-hosting.md`.

## درج جنس پشت سر هم (۲۶ عقرب ۱۴۰۵، خواست صاحب پروژه هنگام درج اجناس)
- **ریدایرکت:** ساختن جنس ← فورم خالی «ایجاد جنس» (`CreateProduct::getRedirectUrl`) با اعلان سبز + دکمه‌ی «ویرایش همین جنس»؛ ذخیره‌ی ویرایش ← لیست اجناس (`EditProduct`).
- **عکس بعد از خطا:** Filament موقع ذخیره، فایل‌های `FileUpload` را در `storage/app/private/tmp/product-uploads` می‌نویسد و فقط مسیر را در state می‌گذارد. `ProductFormData::persist` آن‌ها را در `finally` پاک می‌کرد، پس بعد از خطای سرویس (مثلاً مشخصه‌ی لازم) state به فایل پاک‌شده اشاره می‌کرد. حالا فقط بعد از موفقیت پاک می‌شود؛ باقی‌مانده‌ها را `uploads:prune-temp` (schedule، روزانه ۰۳:۳۰؛ روی سرور cron `schedule:run` لازم است) پاک می‌کند.
- **عکس لیست:** `ImageColumn` با `state` آدرس کامل (`/storage/…`) دوباره پیشوند `/storage/` می‌خورد؛ حالا `->disk('public')` و `ProductImage::pathFor(300)` (مسیر دیسک).
- **نقش‌ها:** چهار نقش از قبل بود (owner، manager، staff، editor). خانه‌ی نقش برای آخرین صاحب فعال قفل است و حالا دلیلش را می‌گوید. نقش تازه لازم نشد؛ «کارمند سفارش» با ۴-۴ (سفارش‌ها در پنل) کار پیدا می‌کند.

## اصلاح «مشخصات جنس» و عکس نمونه (۲۶ عقرب ۱۴۰۵)
- **اشکال:** در فورم جنس، «وضعیت: نو» را در مشخصات انتخاب می‌کردند و با «ذخیره» می‌گفت «مشخصه‌ی وضعیت برای جنس فعال لازم است». **علت (در مرورگر واقعی با Edge بی‌سر بازسازی شد):** خانه‌های مشخصات بعد از انتخاب دسته ساخته می‌شدند (`Section::schema(Closure)`)؛ انتخابِ لیستِ Filament در خانه‌ای که بعد از بارگذاری ساخته شده به سرور نمی‌رسید (خانه‌ی متنی می‌رسید). در تست‌های Livewire دیده نمی‌شد چون تست مقدار را مستقیم در سرور می‌گذارد. **درمان:** همه‌ی خانه‌های مشخصات از اول ساخته می‌شوند و فقط `visible()` آنها به دسته بستگی دارد (`ProductForm::specFields/applicableSpecs`)؛ خانه‌ی پنهان ذخیره نمی‌شود. در مرورگر واقعی: «نو» انتخاب ← ذخیره ← `specs=1`.
- **درس:** برای خانه‌های وابسته به انتخاب دیگر، خانه را از اول بساز و `visible()` بده؛ داخل closure نساز. و رفتار واقعی مرورگر را با مرورگر امتحان کن، نه فقط Livewire test.
- اعلان خطای تکراری: `AdminNotify::validationFailed` اعلان یکسان را در یک درخواست دوباره نمی‌فرستد (اثر انگشت پیام در `request()->attributes`).
- **عکس نمونه:** پوشه‌ی `resources/sample-images/` (۱۵ عکس AVIF، حدود ۸۰۰ کیلوبایت) را صاحب پروژه برای درج در سیستم گذاشته. **به GitHub نمی‌رود** (`.gitignore`). به‌اشتباه یک بار در کامیت `f81dcb3` رفته بود؛ در کامیت بعدی از git بیرون کشیده شد (فایل‌ها روی لپتاپ ماند). در تاریخچه‌ی GitHub آن یک کامیت هنوز هست؛ اگر خواستند با بازنویسی تاریخچه (force push) پاک می‌شود، ولی عکس محصول است و راز نیست.
- قاعده‌ی کار: هر جنس امتحانیِ من در دیتابیس لپتاپ با شناسه و نام دقیق پاک شود، **هیچ‌وقت `forceDelete` روی همه‌ی اجناس** (صاحب پروژه الان جنس واقعی وارد می‌کند).

## اعلان‌های پنل (۲۶ عقرب ۱۴۰۵، خواست صاحب پروژه)
- `App\Support\AdminNotify`: `done()` سبز ۸ ثانیه، `failed()` سرخ ۱۵ ثانیه (اول مانا بود؛ صاحب پروژه خواست خودکار بسته شود)، `validationFailed()` (تا ۵ دلیل + «و n مورد دیگر»). همه‌ی پنل از همین‌جا می‌گذرد.
- در `AppServiceProvider::registerAdminNotifications`: مدت پیش‌فرض ۸ ثانیه؛ وسط بالا؛ `BasePage::$reportValidationErrorUsing` برای خطای فورم‌های Filament (اعتبارسنجی زنده‌ی یک خانه در blur اعلان نمی‌دهد: با `debug_backtrace` تشخیص `validateOnly`)؛ `ToggleColumn::configureUsing` برای «ذخیره شد» روی سوییچ جدول‌ها.
- خطای قاعده‌های سرویس جنس (SKU تکراری…) از مسیر `validate()` نمی‌گذرد؛ `ProductFormData::persist` خودش `AdminNotify::validationFailed` را صدا می‌زند.
- نکته: `Notification::body()` فقط رشته می‌گیرد (نه HtmlString)؛ `<br>` را Filament پاک‌سازی می‌کند. در تست‌ها اعلان‌ها از `NotificationMailbox` + `Livewire\Notifications` خوانده می‌شود (نه از session خام).
- در مرورگر واقعی (Edge بی‌سر) دیده شد: اعلان سبز «ذخیره شد» بعد از ذخیره‌ی کرایه و اعلان سرخ با دلیل‌ها وقتی فورم جنس خالی فرستاده شود.

## AVIF و شماره‌ی تلفن مدیر (۲۶ عقرب ۱۴۰۵)
- `ImageStore`: `supportsAvif()`، `storeAsAvif()`، `storeResponsive(..., withAvif: true)` (خروجی `avif`)؛ ورودی AVIF؛ `allowedTypes()` از روی محتوای فایل. `product_images.avif_sizes` (migration `2026_11_19_000200`)؛ `ProductImage::avifSrcset()`، `files()` شامل AVIF؛ `<picture>` در کارت جنس و صفحه‌ی جنس. `config('store.images.avif')` خاموش‌کن. تست‌ها اگر GD سرور AVIF نداشته باشد skip می‌شوند.
- **اندازه‌گیری** (عکس آزمایشی ۳۰۰۰×۲۰۰۰، ۶۸۶ KB): ۱۲۰۰px WebP ۲۸ KB / AVIF ۲۲ KB؛ ۶۰۰px ۱۴ / ۱۱؛ ۳۰۰px ۶ / ۶. رمزگذاری AVIF حدود ۶ برابر کندتر از WebP (۰٫۳ ثانیه).
- `EditProfile` («حساب من»): شماره‌ی تلفن قابل ویرایش (اعتبارسنجی موبایل افغانستان، یکتا، شکل استاندارد)؛ عوض کردن شماره یا رمز «رمز فعلی» را می‌خواهد؛ کاربر رمز اولیه هم شماره و رمز را یک‌جا می‌گذارد. دلیل اشکال: شماره قفل بود و `EnsurePasswordChanged` کاربر را فقط به همان صفحه می‌فرستاد.

## مرحله‌ی ۴-۱ (سبد) و تحویل حضوری ساخته شد (۲۶ عقرب ۱۴۰۵)
- migration `2026_11_19_000100_create_carts_tables`؛ `Cart`، `CartItem`؛ `CartService` (`find`، `resolve`، `count`، `add`، `setQty`، `remove`، `merge`، `lines`، `totals`، `deliveryEstimate`، `maxQty`) و `CartLine`؛ `CartController` (فورم‌های ساده). جزئیات: ARCHITECTURE.md بخش ۷-۳.
- ادغام سبد مهمان با عضو در `find()` انجام می‌شود، نه در کنترلر ورود؛ پس هر راه ورود (ورود، ثبت‌نام) پوشش داده می‌شود.
- شمارنده‌ی سربرگ: `CartService` «scoped» است و شمارنده با شناسه‌ی شیء درخواست cache می‌شود (در تست‌ها بین درخواست‌ها نشت نکند).
- نکته‌ی تست: `withCookie` در کل تست می‌ماند؛ برای «بازدیدکننده‌ی دیگر» `$this->defaultCookies = []`. کوکی پاسخ با `getCookie(name)` (رمزگشایی‌شده) خوانده می‌شود. `reserved_qty` عمداً mass-assignable نیست (`forceFill`).
- **تحویل حضوری بی کرایه** (خواست صاحب پروژه): دو تنظیم `delivery.pickup_enabled`/`delivery.pickup_note` در «تنظیمات فروشگاه» (بخش تازه‌ی «تحویل حضوری»)؛ `StoreInfo::pickupEnabled/pickupAddress/pickupNote`؛ `DeliveryCalculator::pickupFee()` = ۰؛ نمایش در سبد و صفحه‌ی جنس. فقط با آدرس دفتر نوشته‌شده دیده می‌شود. انتخابش در تسویه (۴-۳) است.

## کرایه‌ی تحویل «روز + وزن» ساخته شد (۲۶ عقرب ۱۴۰۵، به خواست صاحب پروژه)
- **تصمیم:** مثل دیجی‌کالا مشتری روز تحویل را انتخاب می‌کند؛ زودترین روز گران‌تر و هر چه دیرتر ارزان‌تر؛ بار سنگین‌تر اضافه‌ی بیشتر. (اول ۳۰۰ برای زودترین روز بود؛ بعد صاحب پروژه گفت «کمی گران است»: بار تا ۵ کیلو حداکثر ۲۰۰، بالاتر ۲۵۰ تا ۸۰۰.) جزئیات و فرمول: ARCHITECTURE.md بخش ۷-۶.
- migration `2026_11_18_000300_create_delivery_tables`: `delivery_zones`، `delivery_slots`، `delivery_weight_tiers`. مدل‌ها `DeliveryZone`، `DeliverySlot`، `DeliveryWeightTier` (تغییرشان در تاریخچه ثبت می‌شود).
- `DeliveryCalculator` (`zoneFor`، `totalWeight`، `weightFee`، `options`، `option`، `feeRange`) و `DeliveryOption`. زمان به وقت کابل (`store.timezone`)؛ جمعه رد می‌شود؛ بعد از ساعت ۱۶ از پس‌فردا. پول با bcmath. پله‌ها به هر ترتیب کار می‌کنند.
- `DeliveryZoneSeeder`: «کابل» با روزها ۲۰۰، ۱۷۵، ۱۵۰، ۱۲۵، ۱۰۰ و پله‌های ۵/۱۰/۲۰/۵۰ کیلو (۰، ۱۵۰، ۲۵۰، ۴۰۰) و بیشتر ۶۰۰. **قید صاحب پروژه (۲۶ عقرب):** بار تا ۵ کیلو حداکثر ۲۰۰؛ بالاتر ۲۵۰ تا ۸۰۰ (اول ۳۰۰ و ۸۰۰ بود؛ «کمی گران» گفتند). migration `2026_11_18_000400_lower_default_delivery_fees` عددهای قدیمیِ دست‌نخورده را عوض می‌کند و اگر مدیر چیزی را عوض کرده باشد دست نمی‌زند. بی ثبت در تاریخچه، بی‌خطر برای تکرار؛ یک تست قید را برای همه‌ی وزن‌ها و روزها نگه می‌دارد.
- پنل «کرایه‌ی تحویل» (صاحب و مدیر): کرایه‌ی هر روز، پله‌های وزنی (در فورم کیلو، در دیتابیس گرام؛ ارقام فارسی قبول)، ساعت قطع، روزهای بی تحویل، وزن پیش‌فرض، سقف وزن. قاعده‌ها: فقط یک پله‌ی بی عدد (هر جا که باشد؛ اول «فقط آخر» بود و اشتباه سخت‌گیرانه بود)، وزن تکراری نه (پیام شماره‌ی پله را می‌گوید)، کرایه عدد و صفر یا بیشتر، یک منطقه برای هر ولایت.
- هنوز به تسویه وصل نیست (مرحله‌ی ۴-۳): تسویه `options()` را نشان می‌دهد، بعد از انتخاب `option()` دوباره حساب می‌کند و `delivery_date`، `delivery_fee`، `delivery_weight_grams` را در سفارش کاپی می‌کند.
- **لوگو:** همان لوگوی روشن فعلی ماند؛ `bazarak-icon.svg` (۹۰۰ بایت) آیکون تب مرورگر شد و به `manifest` اضافه شد.

## آنچه در مرحله‌ی ۳-۳ ساخته شد (۲۶ عقرب ۱۴۰۵)
- **قاعده‌ی «مشتری چه می‌بیند»** یک جا در `App\Services\Catalog\ProductCatalog`: جنس فعال + زمان انتشار رسیده + قیمتِ نوعِ فعال دارد + دسته و همه‌ی بالادست‌های دسته فعال + (خود بازارک یا فروشنده‌ی تأییدشده). ناموجود در لیست می‌ماند ولی آخر می‌آید. `visibleCategoryIds()` یک پرسش کوچک است (جدول دسته‌ها کم‌حجم).
- `ListingFilters`: فیلتر و مرتب‌سازی از آدرس خوانده و پاک‌سازی می‌شود (ورودی ناجور بی‌اثر، نه خطا؛ `?q[]=x` هم). آدرس‌ها: `?q=&brand[]=&min=&max=&in_stock=1&f[مشخصه][]=گزینه&sort=new|popular|cheap|expensive|relevance`. بین گزینه‌های یک مشخصه «یا»، بین مشخصه‌ها «و». ارقام فارسی در قیمت قبول است.
- **جستجو** `/search`: همه‌ی کلمه‌ها باید در `search_text` (نام، برند، دسته‌ها، SKU، مشخصات) بیایند (`LIKE`؛ برای چند هزار جنس کافی است. اگر آهسته شد، ایندکس FULLTEXT که از قبل هست و `MATCH … AGAINST` جایش را می‌گیرد). دسته‌های هم‌نام بالای نتیجه. حداکثر ۶۰ بار در دقیقه از یک IP (`storefront-search`). صفحه‌ی جستجو و صفحه‌ی فیلترشده `noindex` است؛ canonical دسته بی فیلتر.
- **صفحه‌ی جنس:** `ProductPage` داده را آماده می‌کند (قیمت هر نوع از سرور فرمت می‌شود؛ جاوااسکریپت هیچ محاسبه‌ی پولی ندارد). `Alpine.data('productPicker')` در `resources/js/app.js`: انتخاب گزینه، اگر ترکیب نبود نزدیک‌ترین نوع موجود، خط‌خورده برای ناموجود، لینک واتساپ با نوع و قیمت. بازدید هر نشست یک بار شمرده می‌شود (`views_count`، بی ثبت در تاریخچه). مدیر پنل پیش‌نویس را با نوار زرد «پیش‌نمایش» می‌بیند (noindex).
- ریدایرکت ۳۰۱ آدرس قدیمی جنس؛ جنس حذف‌شده و جنس دسته‌ی خاموش و جنس فروشنده‌ی بسته‌شده ۴۰۴.
- JSON-LD `Product` (`Offer` یا `AggregateOffer` با افغانی و موجودی) با `JSON_HEX_TAG`.
- صفحه‌بندی فارسی (`resources/views/vendor/pagination/bazarak.blade.php`، `Paginator::defaultView`).
- **اشکال که با تست و دیدن واقعی پیدا و درست شد:** `ProductImage::altText()` رابطه‌ی `product` را تنبل بارگذاری می‌کرد (در حالت توسعه خطای ۵۰۰)؛ حالا نام جنس را به‌عنوان جایگزین می‌گیرد. تست «همه‌ی صفحه‌ها با عکس» جلوی تکرارش را می‌گیرد.
- نکته: چون `Model::shouldBeStrict` در توسعه روشن است، هر رابطه‌ای که در لیست لازم است باید `with(...)` شود.
- نکته: فروشگاه «بسته» (`store.is_open` خاموش) فقط پیام «به‌زودی» صفحه‌ی اصلی را نشان می‌دهد؛ اجناس فعال همیشه دیده می‌شود. صاحب پروژه وقتی آماده بود «باز بودن فروشگاه» را روشن می‌کند.
- کارهای مانده: «افزودن به سبد» (مرحله‌ی ۴، دکمه‌ی غیرفعال «به‌زودی» هست)؛ نظر و امتیاز؛ مقایسه؛ شمارنده‌ی هر گزینه‌ی فیلتر؛ بنر (۳-۴).

## آنچه در مرحله‌ی ۳-۲ ساخته شد (۲۶ عقرب ۱۴۰۵)
- migration `2026_11_18_000200_create_products_tables`: `products` (uuid، `seller_id` خالی = جنس خود بازارک، slug یکتا، status: پیش‌نویس/فعال/غیرفعال، cache: `min_price`، `max_price`، `min_compare_price`، `total_stock`، `has_variants`، `search_text` با FULLTEXT)، `product_variants`، `variant_attribute_values`، `product_attribute_values`، `product_images`، `product_relations`، `slug_redirects`.
- `App\Services\Catalog\ProductService::save(?Product, array, ?User)`: **تنها راه نوشتن جنس** (فورم پنل، بعداً پنل فروشنده و واردکردن گروهی). یک transaction: جنس، نوع‌ها (قاعده‌ها: حداقل یک نوع، ترکیب سایز/رنگ تکراری نه، از هر مشخصه یک گزینه، گزینه فقط از مشخصه‌های نوع‌سازِ همان دسته، قیمت قبلی > قیمت، موجودی منفی نه، SKU یکتا و خودکار `BZ-000123-1`)، مشخصات توصیفی (لازم‌ها فقط برای جنس «فعال»)، جنس مرتبط، آدرس (از نام؛ تکراری ← `-2`؛ آدرسِ دستیِ تکراری ← خطا؛ نام عوض شد آدرس نمی‌ماند عوض)، ریدایرکت آدرس قدیمی در `slug_redirects`، و بعد `refresh()` (cache).
- `ProductService::refresh()` و `setStatus()`؛ فروشنده فقط «تأییدشده» می‌تواند صاحب جنس باشد.
- `ProductImageService`: هر عکس ← WebP در ۳۰۰/۶۰۰/۱۲۰۰ (عکس کوچک بزرگ نمی‌شود؛ تا ۱۰ عکس؛ فایل غیرعکس رد)؛ عکس اول = اصلی؛ حذف عکس فایل‌ها را هم پاک می‌کند.
- `PricingService` + `PriceInfo`: قیمت، قیمت قبلی، درصد (گرد به پایین) و مبلغ تخفیف، و «از … تا …» برای چند نوع. کمپاین‌ها در مرحله‌ی ۵ همین‌جا اضافه می‌شود.
- مدل‌ها: `Product` (`published()` scope: فعال + زمان انتشار رسیده + خود بازارک یا فروشنده‌ی تأییدشده)، `ProductVariant` (`cost_price` مخفی از JSON؛ موجودی قابل فروش = موجودی − رزرو)، `ProductImage` (`url($width)`، `srcset()`)، `SlugRedirect`.
- پنل «اجناس»: لیست (عکس، نام، دسته، برند، قیمت یا بازه، موجودی، وضعیت، فروشنده؛ جستجو در `search_text` یعنی نام و برند و دسته و SKU؛ فیلتر وضعیت/دسته با زیردسته‌ها/برند/مالک/ناموجود/حذف‌شده؛ عمل گروهی فعال و غیرفعال با تأیید)؛ فورم (معلومات، عکس، نوع‌ها با «ساختن همه‌ی ترکیب‌ها»، مشخصات خودکار از دسته، توضیحات، جنس مرتبط، آدرس و گوگل). خطای قاعده‌ها روی همان ردیف نوع نشان داده می‌شود. اجازه: صاحب، مدیر، ویرایشگر (`ProductPolicy`).
- نکته‌ی فنی: قیمت و موجودی در فورم متن هستند (`inputMode=decimal`) تا ارقام فارسی هم قبول شود؛ `Num::parse` در سرویس. فایل بارگذاری‌شده‌ی موقت در `storage/app/private/tmp/product-uploads` می‌رود و بعد از ذخیره پاک می‌شود.
- نکته‌ی فنی: ردیف نوعِ تازه در فورم `is_active` را از پیش‌فرض Toggle می‌گیرد؛ در تست‌ها باید صریح بدهید.
- کارهای مانده برای بعد: ریدایرکت آدرس **دسته** (جدول `slug_redirects` برای `category` هم آماده است)؛ سقف عکس، «کپی کردن جنس»؛ واردکردن گروهی از فایل؛ تاریخ شمسی در انتخاب زمان انتشار؛ ثبت حرکت موجودی (`stock_movements`) در مرحله‌ی ۴.

## آنچه در مرحله‌ی ۳-۱ ساخته شد
| بخش | کجا |
|---|---|
| جدول‌ها | `database/migrations/2026_11_17_000200_create_brands_and_attributes_tables.php` |
| مدل‌ها | `Brand`، `Attribute` (فقط `select` نوع‌ساز می‌شود)، `AttributeOption`؛ `Category::catalogAttributes()` و `Category::effectiveAttributes()` (ارث‌بری) |
| پنل | `Resources/Brands/*` (پنجره‌ای)، `Resources/Attributes/*` (گزینه‌ها با Repeater)، `Resources/Categories/RelationManagers/CategoryAttributesRelationManager.php` |
| داده‌ی اولیه | `database/seeders/CatalogAttributeSeeder.php` |
| تست | `tests/Feature/CatalogAttributeTest.php` |

- نام رابطه **`catalogAttributes`** است، نه `attributes` (با `$attributes` داخلی Eloquent قاطی می‌شد). در `Category`، کلاس cast با نام `CastAttribute` وارد شده تا با مدل `Attribute` قاطی نشود.

## نکته‌های فنی بخش ۲ج
- **مرحله‌ی ۴ باید:** هنگام «تحویل شد» برای هر ردیف سفارش `CommissionService::recordSale(new SaleLine('order_item', $item->id, $seller, $category, $itemsAmount))` را صدا بزند (کرایه را نفرستد) و نرخ/مبلغ برگشتی را روی `order_items` کپی کند؛ هنگام مرجوعی `recordReversal($entry, 'order_return:'.$return->id, $amount)`. لغو هیچ کاری ندارد.
- `Settings::get($key, $default)`: اگر `$default` بدهید، پیش‌فرض `config/store.php` نادیده گرفته می‌شود. برای کلیدهای `marketplace.*` بی پیش‌فرض صدا بزنید.
- اگر صفحه‌ای بعد از تغییر Blade کهنه نشان داده شد: `php artisan view:clear` (یک بار پیش آمد).
- انتخاب تاریخ در پنل هنوز میلادی است (Filament تقویم شمسی ندارد)؛ نمایش همه‌جا شمسی است.

## نکته‌های فنی بخش ۲ب
- ورود مشتری با `auth('customer')`؛ `auth()` بی نام همان کاربر پنل است. `redirectGuestsTo` ← `/login` و `redirectUsersTo` ← `/account` در `bootstrap/app.php` (پنل Filament ورود خودش را دارد).
- `ActivityLogger` کننده‌ی کار را خودش پیدا می‌کند: اول کاربر پنل، بعد مشتری (`actor_type = customer`). `ActivityLog::actorName()` برای نمایش.
- همه‌ی قالب‌های `storefront.*` متغیر `$store` (StoreInfo) را از View composer دارند.
- ترتیب درختی لیست دسته‌ها در پنل با دو join (`CategoriesTable::treeOrder`) — اگر روزی بیش از ۳ سطح خواستیم، این باید عوض شود.
- `Seller::$attributes` پیش‌فرض‌ها را دارد (`status=draft`، `onboarding_step=1`)؛ فروشنده‌ی هر مشتری با `SellerOnboarding::forCustomer()` (همیشه از دیتابیس).
- Intervention Image لازم نشد: GD برای WebP و چند اندازه کافی است (یک بسته‌ی کمتر).

## تغییر نسبت به طرح اولیه‌ی دیتابیس
- اضافه در ۲ب: جدول `sellers`؛ `customers` و `categories` جلوتر از برنامه.
- اضافه: `users.password_changed_at`، `activity_log.subject_label`، `provinces.delivery_enabled`.
- حذف از طرح: همه‌ی ستون‌های `erp_*`، جدول‌های `sync_*`، `personal_access_tokens` (Sanctum نصب نشد).
