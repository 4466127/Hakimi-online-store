# پیشرفت کار — بازارک (فروشگاه آنلاین برادران حکیمی)

> این فایل می‌گوید **چه شد و چرا**. قدم بعدی در `todo.md`. اگر جلسه‌ی تازه شروع شد، اول `CLAUDE.md`، بعد این فایل و `todo.md` را بخوان.
> آخرین به‌روزرسانی: ۲۶ عقرب ۱۴۰۵ (۲۰۲۶-۱۱-۱۷)

## وضعیت حالا
- مرحله‌ی ۱ (اسناد) ✓ تأیید شد. مرحله‌ی ۲ (پایه) ✓ ساخته شد. بخش ۲ب (بازارک: نام و لوگو، دسته‌ها، حساب مشتری، «در بازارک بفروشید») ✓. بخش ۲ج (دسته‌های بیشتر، کمیسیون، حساب و تسویه‌ی فروشندگان، مزایا، راهنمای AWS) ✓. بخش ۲د (کلمه‌های افغانستانی، تحویل فقط کابل، موبایل و اپ وب) ✓. **مرحله‌ی ۳-۱ (برندها، مشخصه‌ها، مشخصه‌های هر دسته) ✓. قدم بعدی: ۳-۲ (جنس و نوع‌ها).**
- `php artisan test` → ۱۷۸ تست سبز. `vendor\bin\pint` → مرتب.
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
