<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * داده‌ی «پول نقد گرفته شد» (از فورم پنل یا از پیک).
 *
 * محل گرفتن پول از خود سفارش حساب می‌شود (حضوری ← نمایندگی، درِ خانه ← دمِ در)، نه از فورم؛
 * مبلغ هم با مبلغ سفارش مقایسه می‌شود و اگر یکی نبود دلیل لازم است.
 */
final class CashCollection
{
    public function __construct(
        /** کدی که مشتری گفته (ارقام فارسی هم قبول است). */
        public readonly ?string $deliveryCode,
        /** پول واقعی گرفته‌شده. */
        public readonly string|int|float $amountReceived,
        /** نمایندگی‌ای که پول در آن گرفته شد (خالی = نمایندگی خود سفارش). */
        public readonly ?int $collectionPointId = null,
        /** کسی که پول را داد (اگر خود گیرنده نبود). */
        public readonly ?string $payerName = null,
        /** دلیل اختلاف مبلغ یا هر یادداشت. */
        public readonly ?string $note = null,
        /** همراه گرفتن پول، جنس هم تحویل داده شد (پیش‌فرض: بله). */
        public readonly bool $markDelivered = true,
    ) {}
}
