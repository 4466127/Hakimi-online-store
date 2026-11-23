<?php

declare(strict_types=1);

namespace App\Services\Orders;

/**
 * اطلاعات تسویه که کنترلر بعد از اعتبارسنجی می‌دهد. هیچ مبلغی اینجا نیست:
 * همه‌ی مبلغ‌ها (قیمت، کرایه، جمع) در سرور از نو حساب می‌شود.
 */
final readonly class CheckoutData
{
    public function __construct(
        public string $purchaserName,
        public string $purchaserPhone,
        public string $recipientName,
        public string $recipientPhone,
        /** delivery (درِ خانه) | pickup (حضوری) */
        public string $deliveryMethod,
        public string $paymentMethodCode,
        public ?string $purchaserEmail = null,
        public string $purchaserCountry = 'AF',
        public bool $isGift = false,
        public ?int $provinceId = null,
        public ?string $city = null,
        public ?string $district = null,
        public ?string $addressLine = null,
        public ?string $deliveryInstructions = null,
        /** روز تحویل انتخاب‌شده (Y-m-d)؛ فقط برای درِ خانه */
        public ?string $deliveryDate = null,
        /** نمایندگی برای تحویل حضوری؛ خالی = اولین نمایندگی فعال */
        public ?int $collectionPointId = null,
        public ?string $customerNote = null,
        public ?int $customerId = null,
        public string $source = 'web',
        public string $locale = 'fa',
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
