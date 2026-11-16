<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;

/**
 * تنها جایی که «قیمت نمایشی» ساخته می‌شود. فعلاً: قیمت نوع + قیمت قبلی (تخفیف دستی).
 * در مرحله‌ی ۵ کمپاین‌ها و کوپن‌ها همین‌جا اضافه می‌شود (بیشترین تخفیف؛ جمع نمی‌شود)،
 * بی تغییر در صفحه‌ها و سبد.
 */
class PricingService
{
    /** قیمت یک نوع. */
    public function forVariant(ProductVariant $variant): PriceInfo
    {
        return $this->build((string) $variant->price, $variant->compare_at_price);
    }

    /**
     * قیمت کارت جنس: ارزان‌ترین نوع فعال (با cache روی خود جنس). جنس بی قیمت (بی نوع فعال) null می‌دهد.
     */
    public function forProduct(Product $product): ?PriceInfo
    {
        if ($product->min_price === null) {
            return null;
        }

        $info = $this->build((string) $product->min_price, $product->min_compare_price);
        $isRange = $product->max_price !== null && Money::compare($product->max_price, $product->min_price) > 0;

        return $isRange
            ? new PriceInfo($info->price, $info->comparePrice, $info->discountPercent, $info->saving, true)
            : $info;
    }

    private function build(string $price, ?string $comparePrice): PriceInfo
    {
        $price = Money::of($price);
        $percent = Money::discountPercent($price, $comparePrice);

        if ($percent === 0 || $comparePrice === null) {
            return new PriceInfo($price);
        }

        return new PriceInfo($price, Money::of($comparePrice), $percent, Money::sub($comparePrice, $price));
    }
}
