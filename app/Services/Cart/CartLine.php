<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Catalog\PriceInfo;

/**
 * یک خط سبد با قیمت و موجودی زنده (سبد خودش قیمت ذخیره نمی‌کند).
 *
 * problem: null | 'unavailable' (جنس یا نوع دیگر فروخته نمی‌شود) | 'out_of_stock' | 'insufficient' (تعداد بیشتر از موجودی)
 * maxQty: بیشترین تعداد قابل سفارش همین حالا (۰ = نمی‌شود).
 */
final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public ProductVariant $variant,
        public Product $product,
        public ?ProductImage $image,
        public int $qty,
        public PriceInfo $price,
        public string $lineTotal,
        public string $lineSaving,
        public int $weightGrams,
        public int $maxQty,
        public ?string $problem,
    ) {}

    /** این خط در جمع و سفارش حساب می‌شود؟ */
    public function counts(): bool
    {
        return $this->problem === null || $this->problem === 'insufficient';
    }
}
