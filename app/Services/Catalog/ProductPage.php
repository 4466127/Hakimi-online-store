<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StoreInfo;
use App\Support\Money;
use App\Support\Num;
use Illuminate\Support\Collection;

/**
 * داده‌ی آماده‌ی صفحه‌ی جنس: نوع‌ها و گزینه‌های انتخاب (برای Alpine)، قیمت هر نوع، و JSON-LD گوگل.
 * مبلغ‌ها همین‌جا فرمت می‌شود تا جاوااسکریپت فقط متن را نشان بدهد (بی محاسبه‌ی پول در مرورگر).
 */
class ProductPage
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly StoreInfo $store,
    ) {}

    /**
     * @return array{
     *   variants: list<array<string, mixed>>,
     *   groups: list<array{id: int, name: string, options: list<array{id: int, value: string, hex: string|null}>}>,
     *   picker: array<string, mixed>,
     *   current: array<string, mixed>|null,
     *   images: list<array{url: string, thumb: string, srcset: string, avif: string, alt: string, width: int, height: int}>,
     *   jsonLd: array<string, mixed>
     * }
     */
    public function build(Product $product): array
    {
        $variants = $product->variants()->where('is_active', true)->with('options')->get();
        $rows = $variants->map(fn (ProductVariant $v): array => $this->variantRow($v))->values()->all();

        $default = $variants->firstWhere('is_default', true) ?? $variants->first(fn (ProductVariant $v) => $v->isAvailable()) ?? $variants->first();
        $defaultRow = $default !== null ? $this->variantRow($default) : null;

        $groups = $this->groups($variants);
        $attributeIds = array_column($groups, 'id');

        return [
            'variants' => $rows,
            'groups' => $groups,
            'current' => $defaultRow,
            'picker' => [
                'attrs' => $attributeIds,
                'groups' => $groups,
                'variants' => $rows,
                'selected' => $defaultRow['options'] ?? (object) [],
                'whatsapp' => $this->store->whatsappLink(),
                'message' => (string) __('store.product.whatsapp_message', ['name' => $product->tr('name')]),
                'url' => $product->url(),
            ],
            'images' => $this->images($product),
            'jsonLd' => $this->jsonLd($product, $variants),
        ];
    }

    /** @return array<string, mixed> */
    private function variantRow(ProductVariant $variant): array
    {
        $price = $this->pricing->forVariant($variant);
        $available = $variant->isAvailable();
        $low = $available && $variant->isLowStock() && ! $variant->allow_backorder;

        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'label' => (string) $variant->label,
            'options' => (object) $variant->options->mapWithKeys(fn ($o) => [(int) $o->pivot->attribute_id => (int) $o->id])->all(),
            'price' => Money::format($price->price),
            'compare' => $price->comparePrice !== null ? Money::format($price->comparePrice) : null,
            'badge' => $price->hasDiscount() ? (string) __('store.product.discount_badge', ['percent' => Num::toFa((string) $price->discountPercent)]) : null,
            'available' => $available,
            'low' => $low,
            'leftText' => $low ? (string) __('store.product.few_left', ['n' => Num::format($variant->availableQty(), 0)]) : null,
        ];
    }

    /**
     * مشخصه‌ها و گزینه‌های سایز/رنگ که در نوع‌های فعال هست.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return list<array{id: int, name: string, options: list<array{id: int, value: string, hex: string|null}>}>
     */
    private function groups($variants): array
    {
        $byAttribute = [];
        foreach ($variants as $variant) {
            foreach ($variant->options as $option) {
                $byAttribute[(int) $option->pivot->attribute_id][$option->id] = $option;
            }
        }
        if ($byAttribute === []) {
            return [];
        }

        $groups = [];
        foreach (Attribute::query()->whereIn('id', array_keys($byAttribute))->orderBy('position')->orderBy('id')->get() as $attribute) {
            $options = collect($byAttribute[$attribute->id])->sortBy(fn ($o) => [$o->position, $o->id])->values();
            $groups[] = [
                'id' => $attribute->id,
                'name' => $attribute->tr('name'),
                'options' => $options->map(fn ($o): array => ['id' => (int) $o->id, 'value' => $o->tr('value'), 'hex' => $o->color_hex])->all(),
            ];
        }

        return $groups;
    }

    /** @return list<array{url: string, thumb: string, srcset: string, avif: string, alt: string, width: int, height: int}> */
    private function images(Product $product): array
    {
        $name = $product->tr('name');

        return $product->images()->get()->map(fn ($image): array => [
            'url' => $image->url(600),
            'thumb' => $image->url(300),
            'srcset' => $image->srcset(),
            'avif' => $image->avifSrcset(),
            'alt' => $image->altText($name),
            'width' => (int) $image->width,
            'height' => (int) $image->height,
        ])->all();
    }

    /**
     * ساختار schema.org برای گوگل (قیمت و موجودی در نتیجه‌ی جستجو).
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<string, mixed>
     */
    private function jsonLd(Product $product, $variants): array
    {
        $images = $product->images()->get()->map(fn ($image) => url($image->url(1200)))->values()->all();
        $inStock = $variants->contains(fn (ProductVariant $v) => $v->isAvailable());
        $availability = 'https://schema.org/'.($inStock ? 'InStock' : 'OutOfStock');
        $description = strip_tags($product->tr('short_description') ?: $product->tr('description'));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->tr('name'),
            'url' => $product->url(),
        ];
        if ($description !== '') {
            $data['description'] = mb_substr($description, 0, 500);
        }
        if ($images !== []) {
            $data['image'] = $images;
        }
        if ($product->brand !== null) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }
        $first = $variants->first();
        if ($first !== null) {
            $data['sku'] = $first->sku;
        }

        if ($product->min_price !== null) {
            $range = $product->max_price !== null && Money::compare($product->max_price, $product->min_price) > 0;
            $data['offers'] = $range
                ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'AFN', 'lowPrice' => Money::of($product->min_price), 'highPrice' => Money::of($product->max_price), 'offerCount' => $variants->count(), 'availability' => $availability]
                : ['@type' => 'Offer', 'url' => $product->url(), 'priceCurrency' => 'AFN', 'price' => Money::of($product->min_price), 'availability' => $availability];
        }

        return $data;
    }
}
