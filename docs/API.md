# نقطه‌های ورودی (HTTP و سرویس) — سفارش، پرداخت، بازپرداخت

> ۱ قوس ۱۴۰۵. **همه‌ی این‌ها طرح است و هنوز ساخته نشده** (منتظر تأیید `docs/ASSESSMENT_AND_PLAN.md`). نام و فیلد‌های سمت **حساب‌پی** عمداً نوشته نشده؛ بعد از دیدن مستندات رسمی در «درایور» ثبت می‌شود (`docs/PAYMENTS_AND_CHECKOUT.md` بخش ۸).
> سایت فعلاً صفحه‌های Blade است و JSON API جدا ندارد (سبد هم فورم ساده است). برای پنل مدیریت (Filament) «API» نیست: هر عمل یک متد سرویس است که فقط از پنل صدا زده می‌شود و با Policy محافظت می‌شود.

## ۱. صفحه‌های مشتری (web، با CSRF)

> **وضعیت واقعی (۲ قوس ۱۴۰۵، مرحله‌ی ۲-۲ ساخته شد):** `GET/POST /checkout`، `GET /order/{uuid}`، `GET/POST /track` و `GET /account/orders` **ساخته شده‌اند**. نام محدودیت‌ها در کد: `throttle:checkout` (۱۰ در دقیقه از یک IP) و `throttle:order-track` (۱۵ در دقیقه). `collection_point_id` اختیاری است (خالی = اولین نمایندگی فعال). «کلید Idempotency فورم»، لغو توسط مشتری، و پرداخت آنلاین (`/order/{uuid}/pay`، `/payments/{uuid}/return`) **هنوز ساخته نشده‌اند** (مرحله‌های ۳ و ۵).

| متد و مسیر | کار | محافظت |
|---|---|---|
| `GET /checkout` | تسویه‌ی یک‌صفحه‌ای (سبد را می‌خواند) | سبد خالی ← برگشت به سبد |
| `POST /checkout` | ساختن سفارش + `payments` اولیه | `throttle:storefront-checkout` (۱۰/ساعت/IP)، Form Request، کلید `Idempotency-Key` (توکن فورم) تا دو بار زدن دو سفارش نسازد |
| `GET /order/{uuid}` | صفحه‌ی سفارش (وضعیت، پرداخت، تاریخچه) | uuid حدس‌ناپذیر؛ اگر وارد شده: مالک؛ مهمان: فقط با نشانی کامل |
| `POST /order/{uuid}/cancel` | لغو توسط مشتری (فقط `pending`) | مالک؛ دلیل اختیاری |
| `POST /order/{uuid}/pay` | شروع/تکرار پرداخت آنلاین | فقط سفارشی که `unpaid`/`failed` و روش آنلاین دارد؛ `throttle` |
| `GET /payments/{paymentUuid}/return` | صفحه‌ی بازگشت از درگاه | **فقط اطلاع‌رسانی**؛ هیچ وضعیتی را عوض نمی‌کند (حداکثر یک پرسش از درگاه برای تازه‌سازی) |
| `GET/POST /track` | پیگیری با شماره‌ی سفارش + تلفن/ایمیل | `throttle:storefront-track` (۵/دقیقه/IP)، پیام یکسان برای «پیدا نشد» |
| `GET /account/orders` | سفارش‌های من | `auth:customer` |

## ۲. Webhook درگاه

`POST /webhooks/{provider}` (مثلاً `hesabpay`) — **بیرون از CSRF**، فقط HTTPS.
1. محدودیت درخواست جدا؛ بدنه‌ی خام ذخیره می‌شود (در `payment_events`، بدون هدر کلید).
2. **اصالت** طبق مستندات رسمی درگاه بررسی می‌شود (روش امضا/کلید: `[در انتظار مستندات]`). نامعتبر ← `401` + ثبت `signature_valid=false` + هشدار.
3. `(provider, event_id)` یکتا: رویداد تکراری ← `200` بدون اثر (idempotent).
4. پرداخت پیدا می‌شود (`provider_reference` یا `idempotency_key`)؛ مبلغ، ارز و مرجع با سفارش مطابقت داده می‌شود؛ ناهماهنگ ← `failed` + هشدار، نه `paid`.
5. گذار وضعیت با قفل و ماشین وضعیت؛ پاسخ سریع `200` (کارهای سنگین در صف).
6. شکست داخلی ← `5xx` تا درگاه دوباره بفرستد (اگر درگاه تلاش مجدد دارد؛ `[در انتظار مستندات]`).

## ۳. واسط درگاه (PHP)

```php
interface PaymentGateway {
    public function code(): string;                                  // 'hesabpay'
    public function capabilities(): GatewayCapabilities;             // ارزها، کشورها، بازپرداخت کامل/جزئی، webhook
    public function createSession(Payment $p, CheckoutReturnUrls $u): GatewaySession; // آدرس صفحه‌ی میزبانی‌شده + مرجع
    public function verifyWebhook(Request $r): VerifiedEvent;        // بی‌اعتبار ← استثنا
    public function fetchStatus(string $reference): GatewayStatus;   // پرسش سرور-به-سرور (تأیید یا انقضا)
    public function refund(Payment $p, string $amount, string $key): GatewayRefund; // فقط اگر capabilities اجازه دهد
}
```
درایورها: `HesabPayGateway` (مرحله‌ی ۵)، `FakeGateway` (فقط تست؛ **درگاه جعلی در سایت واقعی نیست**)، و `Manual` (حواله) و `Cash` که واسط ندارند و با سرویس تأیید دستی کار می‌کنند.

## ۴. عمل‌های پنل مدیریت (سرویس‌ها)

| سرویس | عمل | دسترسی | دلیل؟ |
|---|---|---|---|
| `OrderService::confirm/process/ship/deliver/cancel` | گذار وضعیت سفارش فروشنده | مدیر، کارمند سفارش (پیک: فقط `deliver`) | لغو و override بلی |
| `PaymentService::recordCashCollection(Payment, amount, user)` | ثبت پول نقد | مدیر، کارمند سفارش، پیک (واگذارشده)، مسئول نقطه | اختلاف مبلغ بلی |
| `PaymentService::confirmTransfer / rejectTransfer` | حواله | مدیر | بلی |
| `RefundService::request / approve / markCompleted / reject` | بازپرداخت | درخواست: کارمند/مدیر؛ تأیید: مدیر/صاحب | بلی |
| `ReturnService::request / receive / close` | برگشت جنس | مدیر، کارمند | بلی |
| `PaymentAllocator::allocate(Payment)` | تقسیم پولِ رسیده بین سفارش‌های فروشنده‌ها (کوچک‌ترین واحد پول، روش «بیشترین باقی‌مانده»، مجموع دقیقاً برابر پول رسیده؛ بی‌تکرار با کلید یکتای `payment_id + seller_order_id + component`) | خودکار (بعد از تأیید پول؛ نه از پنل) | لاگ |
| `CommissionRuleService::create/update` | قاعده‌ی کمیسیون | صاحب/مدیر | بلی (لاگ) |
| `SettlementService::record / adjust` | تسویه و اصلاح مانده | صاحب/مدیر | بلی |

هر کدام: transaction، `lockForUpdate`، ماشین وضعیت، `order_status_history`، `activity_log` (با دلیل)، و بی‌تکراری با `idempotency_key`.

## ۵. خطاها
همه‌ی خطاهای کسب‌وکار `DomainException` با کد پایدار (`stock.insufficient`، `payment.not_collectable`، `payment.already_paid`، `order.transition_denied`…) و پیام دری از `lang/fa`. فورم‌ها خطا را بالای صفحه نشان می‌دهند.
