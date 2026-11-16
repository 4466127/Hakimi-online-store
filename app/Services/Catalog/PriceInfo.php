<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * قیمتی که به مشتری نشان داده می‌شود: قیمت، قیمت قبلی، درصد و مبلغ تخفیف.
 * مبلغ‌ها رشته‌ی دو رقم اعشار به افغانی هستند (هیچ‌وقت float).
 */
final readonly class PriceInfo
{
    public function __construct(
        public string $price,
        public ?string $comparePrice = null,
        public int $discountPercent = 0,
        public string $saving = '0.00',
        /** برای جنس چند نوعی: قیمت‌ها فرق دارد و «از ... افغانی» نشان داده می‌شود. */
        public bool $isRange = false,
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discountPercent > 0 && $this->comparePrice !== null;
    }
}
