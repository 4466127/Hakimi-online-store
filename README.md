# فروشگاه آنلاین برادران حکیمی — Hakimi Online Store

فروشگاه اینترنتی برای فروش اجناس دکان خانوادگی «برادران حکیمی» به مشتریان افغانستان: بوت و کفش، لباس افغانی، موبایل و لوازم، لپتاپ و کمپیوتر.
به دری، راست‌چین، اول برای موبایل و ساخته‌شده برای انترنت ضعیف.

> **وضعیت:** مرحله‌ی ۲ از ۶ ساخته شد (پایه، پنل مدیریت، قالب فروشگاه). قدم بعدی: کاتالوگ اجناس. جزئیات: [ROADMAP.md](ROADMAP.md)

## چه چیزی حالا کار می‌کند

**سایت مشتری** (`/`)
- صفحه‌ی اصلی «به‌زودی» با نام، شعار و دکمه‌های تماس و واتساپ فروشگاه
- نوار خبر: «تحویل فعلاً در کابل و غزنی — بقیه‌ی ولایات زیر کار است» (از پنل قابل تغییر)
- صفحه‌های ثابت (درباره‌ی ما، تماس، شرایط، مرجوعی، حریم خصوصی) در `/page/…`
- سربرگ با جستجو، نوار پایین موبایل، پاورقی؛ صفحه‌های خطا به دری
- هیچ فایل بیرونی (CDN) ندارد: فونت، CSS و JS از خود سرور — CSS حدود ۶ و JS حدود ۲۰ کیلوبایت (فشرده)

**پنل مدیریت** (`/admin`)
- ورود با **شماره‌ی تلفن** و رمز؛ قفل حساب بعد از ۱۰ رمز اشتباه؛ تغییر اجباری رمز اولیه
- چهار نقش: صاحب فروشگاه، مدیر، کارمند سفارش، ویرایشگر اجناس
- تنظیمات فروشگاه (نام، شعار، لوگو، تلفن، واتساپ، ایمیل، آدرس)
- صفحه‌های ثابت با ویرایشگر متن
- ولایت‌ها و «تحویل فعال» (با یک کلیک ولایت تازه باز می‌شود)
- کاربران پنل؛ تاریخچه‌ی فعالیت (چه کسی، چه وقت، چه کرد)

**در مرحله‌های بعد:** اجناس و دسته‌ها با فیلتر و جستجو (۳)، سبد و سفارش (۴)، کرایه‌ی تحویل، پرداخت هنگام تحویل و پرداخت آنلاین با حساب‌پی HesabPay، تخفیف و کوپن (۵)، سرور و راه‌اندازی (۶).

## فناوری

| بخش | انتخاب |
|---|---|
| زبان و فریم‌ورک | PHP 8.4 + Laravel 13 |
| صفحه‌های مشتری | Blade + Tailwind CSS 4 + Alpine.js |
| پنل مدیریت | Filament 5 |
| دیتابیس | MariaDB 10.4 / MySQL 8.4 |
| فونت | Vazirmatn (از خود سرور) |
| تست | PHPUnit — ۹۹ تست |

دلیل هر انتخاب: [ARCHITECTURE.md](ARCHITECTURE.md) بخش ۳.

## نصب روی لپتاپ (Windows + XAMPP)

**پیش‌نیاز:** XAMPP با PHP 8.3 یا بالاتر، [Composer](https://getcomposer.org)، [Node.js LTS](https://nodejs.org)، Git.

۱. **MySQL را روشن کنید:** XAMPP Control Panel را باز کنید و کنار MySQL دکمه‌ی Start را بزنید.

۲. **کد را بگیرید** (در PowerShell):
```powershell
cd C:\xampp\htdocs
git clone https://github.com/4466127/Hakimi-online-store.git hakimi-online-store
cd hakimi-online-store
```

۳. **بسته‌ها را نصب کنید:**
```powershell
composer install
npm install
```

۴. **فایل تنظیمات را بسازید:**
```powershell
copy .env.example .env
php artisan key:generate
```
فایل `.env` را باز کنید و اگر MySQL شما رمز دارد، `DB_PASSWORD` را بنویسید. برای کاربر اول پنل این سه خط را به آخر `.env` اضافه کنید (شماره‌ی خودتان):
```
STORE_OWNER_NAME="نام شما"
STORE_OWNER_PHONE=0700123456
STORE_OWNER_PASSWORD=
```
(اگر `STORE_OWNER_PASSWORD` خالی بماند، یک رمز تصادفی ساخته و در قدم ۶ نشان داده می‌شود.)

۵. **دیتابیس‌ها را بسازید:**
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS hakimi_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS hakimi_store_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

۶. **جدول‌ها و داده‌ی اولیه** (۳۴ ولایت، پول‌ها، صفحه‌های ثابت، کاربر صاحب):
```powershell
php artisan migrate --seed
php artisan storage:link
```
شماره و **رمز اولیه‌ی کاربر صاحب** در همین‌جا نشان داده می‌شود؛ یادداشتش کنید. در اولین ورود باید عوض شود.

۷. **سایت را روشن کنید:**
```powershell
php artisan serve
```
- فروشگاه: <http://127.0.0.1:8000>
- پنل مدیریت: <http://127.0.0.1:8000/admin>

### امتحان با موبایل (در وای‌فای خانه)
```powershell
php artisan serve --host=0.0.0.0
```
بعد در موبایل آدرس `http://<IP لپتاپ>:8000` را باز کنید (IP لپتاپ را با دستور `ipconfig` پیدا کنید؛ چیزی مثل `192.168.1.10`).

## کار روزانه‌ی برنامه‌نویس

| کار | دستور |
|---|---|
| روشن کردن سایت | `php artisan serve` |
| ساختن CSS و JS بعد از تغییر ظاهر | `npm run build` |
| دیدن تغییر ظاهر زنده هنگام کار | `npm run dev` (در ترمینال دوم) |
| همه‌ی تست‌ها | `php artisan test` |
| مرتب کردن کد | `vendor\bin\pint` |
| جدول‌های تازه بعد از گرفتن کد جدید | `php artisan migrate` |

پوشه‌ی `public/build` (CSS و JS ساخته‌شده) عمداً در git هست تا سرور به Node.js نیاز نداشته باشد. بعد از هر تغییر در `resources/css` یا `resources/js` یا قالب‌ها، `npm run build` را بزنید و نتیجه را کامیت کنید.

## ساختار پروژه

```
app/
  Enums/          نقش‌ها و وضعیت‌ها
  Filament/       پنل مدیریت (ورود، منابع، صفحه‌ها، ویجت‌ها)
  Http/           کنترلرهای فروشگاه و middleware
  Models/         مدل‌ها (User, Page, Province, Currency, Setting, ActivityLog)
  Policies/       دسترسی هر نقش
  Services/       منطق کار (Settings, StoreInfo, ActivityLogger, ImageStore)
  Support/        Jalali (تاریخ شمسی)، Num (عدد فارسی)، Phone، Money، Persian
database/         migration، seeder، factory
lang/fa/          همه‌ی متن‌های صفحه (هیچ متنی در کد نیست)
resources/views/  قالب‌های فروشگاه، خطاها، بخش‌های پنل
tests/            تست‌های خودکار
docs/             راهنماها
```

## اسناد

| فایل | برای چه |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | معماری، فناوری، منطق‌های کلیدی، میزبانی، امنیت |
| [DATABASE.md](DATABASE.md) | جدول‌ها و ستون‌ها |
| [ROADMAP.md](ROADMAP.md) | مراحل کار و تصمیم‌ها |
| [progress.md](progress.md) / [todo.md](todo.md) | چه شد / قدم بعدی |
| [CHANGELOG.md](CHANGELOG.md) | لیست تغییرات |
| [docs/rahnama-modir.md](docs/rahnama-modir.md) | راهنمای پنل مدیریت برای اعضای خانواده |
| [CLAUDE.md](CLAUDE.md) | قواعد کار روی این پروژه |

## چند قاعده‌ی مهم

- این فروشگاه **مستقل** است و به سیستم مدیریت دکان وصل نیست.
- قیمت‌ها به افغانی و با `DECIMAL(12,2)` ذخیره می‌شود؛ حساب پول هیچ‌وقت با float نیست (`App\Support\Money`).
- زمان‌ها در دیتابیس UTC است و به وقت کابل و تاریخ شمسی افغانی (حمل… حوت) نشان داده می‌شود.
- شماره‌های تلفن همیشه به شکل `07XXXXXXXX` ذخیره می‌شود؛ مشتری هر طور بنویسد (ارقام فارسی، با `+93`، با فاصله) قبول است.
- هیچ رمز یا کلیدی در git نیست؛ فقط در `.env`.
- عکس‌های بارگذاری‌شده بررسی و از نو ساخته می‌شود (WebP) پیش از ذخیره.

## مجوز

کد این مخزن متعلق به «برادران حکیمی» است. فونت Vazirmatn با مجوز OFL (`public/fonts/vazirmatn/OFL.txt`).
