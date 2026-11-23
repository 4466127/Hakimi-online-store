<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentException;

/**
 * درگاه پرداخت آنلاین (فعلاً فقط **حساب‌پی** در برنامه است؛ مرحله‌ی ۵).
 *
 * قاعده‌ی پروژه: **هیچ درگاه ساختگی ساخته نمی‌شود.** تا حساب پذیرنده (merchant)، کلید API و
 * متن مستندات رسمی حساب‌پی نباشد، تنها پیاده‌سازی موجود `LogGateway` است که هیچ پولی جابه‌جا
 * نمی‌کند و فقط می‌گوید «آماده نیست». وقتی حساب پذیرنده آماده شد، یک فایل درایور تازه
 * (`HesabPayGateway`) همین واسط را پیاده می‌کند و جای دیگری عوض نمی‌شود.
 *
 * شکل کار (از روی مستندات عمومی حساب‌پی؛ جزئیات در docs/hesabpay.md):
 *   ۱. سرور ما «نشست پرداخت» می‌سازد و آدرس صفحه‌ی پرداخت را می‌گیرد (`start`).
 *   ۲. مشتری در صفحه‌ی خود حساب‌پی پول می‌دهد و برمی‌گردد.
 *   ۳. **تنها شاهد پرداخت، جواب سرور-به-سرور (webhook) با امضای بررسی‌شده است** (`verify`)،
 *      نه برگشتن مرورگر مشتری.
 */
interface PaymentGateway
{
    /** کد روش پرداخت در جدول `payment_methods` (مثلاً `hesabpay`). */
    public function code(): string;

    /**
     * درگاه واقعاً پیکربندی و آماده است؟ (کلید در `.env`، آدرس‌ها، حساب پذیرنده)
     * تا این `true` نشود، مشتری نمی‌تواند این روش را انتخاب کند.
     */
    public function isReady(): bool;

    /**
     * شروع پرداخت: ساختن نشست در درگاه و گرفتن آدرس صفحه‌ی پرداخت.
     *
     * @throws PaymentException
     */
    public function start(Payment $payment, Order $order): GatewayStart;

    /**
     * بررسی اصالت پیام درگاه (امضا) و تبدیلش به نتیجه‌ی قابل اعتماد.
     * پیام بی‌امضا یا با امضای غلط هرگز «پرداخت‌شده» نمی‌شود.
     *
     * @param  array<string, string>  $headers
     *
     * @throws PaymentException
     */
    public function verify(string $payload, array $headers): GatewayEvent;
}
