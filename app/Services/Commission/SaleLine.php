<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\Category;
use App\Models\Seller;
use DateTimeInterface;

/**
 * یک ردیف فروش تحویل‌شده که کمیسیونش ثبت می‌شود (در مرحله‌ی ۴ از ردیف سفارش ساخته می‌شود).
 *
 * $itemsAmount = قیمت جنس × تعداد − تخفیف. **کرایه‌ی تحویل جدا داده می‌شود و هیچ‌وقت در
 * حساب کمیسیون نمی‌آید** ($shippingAmount فقط برای روشن بودن اینجاست و استفاده نمی‌شود).
 */
final readonly class SaleLine
{
    public function __construct(
        public string $sourceType,           // order_item
        public int $sourceId,
        public ?Seller $seller,              // null = جنس خود بازارک
        public ?Category $category,
        public string $itemsAmount,
        public string $shippingAmount = '0',
        public ?string $description = null,
        public ?DateTimeInterface $occurredAt = null,
    ) {}

    /** کلید یکتا: یک ردیف سفارش فقط یک بار کمیسیون می‌خورد. */
    public function idempotencyKey(): string
    {
        return $this->sourceType.':'.$this->sourceId.':sale';
    }
}
