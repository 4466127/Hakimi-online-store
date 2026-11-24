<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

/**
 * پیام تأییدشده‌ی درگاه (سرور-به-سرور). تنها شاهد پرداخت آنلاین همین است.
 *
 * `eventId` برای بی‌تکرار بودن است: هر پیام فقط یک بار اثر می‌گذارد (یکتایی
 * `(provider, event_id)` در جدول `payment_events`). برای حساب‌پی = شناسه‌ی تراکنش؛ برای ردِ
 * بی‌تراکنش، ترکیب نشست و دلیل (مستندات: «شناسه‌ی تراکنش از user_id ساخته نشود»).
 */
final class GatewayEvent
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /** آزمون «آدرس پیام‌گیر کار می‌کند؟» از پورتال درگاه — هیچ سفارشی را عوض نمی‌کند. */
    public const VALIDATION = 'validation';

    public function __construct(
        public readonly string $eventId,
        /** شناسه‌ی پرداخت ما که هنگام ساختن نشست فرستاده شد (حساب‌پی: user_id = uuid پرداخت). */
        public readonly string $reference,
        /** paid | failed | pending | validation */
        public readonly string $status,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        /** @var array<string, mixed> پیام خام برای لاگ و تطبیق (بی امضا؛ هرگز کلید یا شماره‌ی کارت) */
        public readonly array $raw = [],
        public readonly ?string $transactionId = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $reason = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isValidation(): bool
    {
        return $this->status === self::VALIDATION;
    }
}
