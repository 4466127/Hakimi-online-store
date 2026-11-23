<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

/**
 * پیام تأییدشده‌ی درگاه (سرور-به-سرور). تنها شاهد پرداخت آنلاین همین است.
 *
 * `eventId` برای بی‌تکرار بودن است: هر پیام فقط یک بار اثر می‌گذارد
 * (یکتایی `(provider, event_id)` در مرحله‌ی ۵ همراه جدول `payment_events` می‌آید).
 */
final class GatewayEvent
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function __construct(
        public readonly string $eventId,
        public readonly string $reference,
        /** paid | failed | pending */
        public readonly string $status,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        /** @var array<string, mixed> پیام خام برای لاگ و تطبیق (هرگز کلید یا شماره‌ی کارت) */
        public readonly array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
