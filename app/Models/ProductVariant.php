<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * یک نوع از جنس (مثلاً «کفش چرمی، سایز ۴۲، سیاه»). قیمت، موجودی و SKU اینجاست.
 *
 * موجودی قابل فروش = stock_qty − reserved_qty (reserved در سفارش‌های باز، مرحله‌ی ۴).
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $label
 * @property string $price
 * @property string|null $compare_at_price
 * @property string $stock_qty
 * @property string $reserved_qty
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'label', 'price', 'compare_at_price', 'cost_price', 'stock_qty',
        'low_stock_threshold', 'allow_backorder', 'weight_grams', 'is_default', 'is_active', 'position', 'image_id',
    ];

    /** cost_price (قیمت خرید) هیچ‌وقت به صفحه‌ی مشتری یا JSON نمی‌رود. */
    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'reserved_qty' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'allow_backorder' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'weight_grams' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant): void {
            $variant->uuid ??= (string) Str::uuid();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'variant_id');
    }

    /** گزینه‌های این نوع (سایز ۴۲، رنگ سیاه) با pivot.attribute_id. */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(AttributeOption::class, 'variant_attribute_values', 'variant_id', 'attribute_option_id')
            ->withPivot('attribute_id');
    }

    /** موجودی قابل فروش (هیچ‌وقت منفی نشان داده نمی‌شود). */
    public function availableQty(): string
    {
        $available = bcsub((string) $this->stock_qty, (string) $this->reserved_qty, 3);

        return bccomp($available, '0', 3) < 0 ? '0.000' : $available;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ($this->allow_backorder || bccomp($this->availableQty(), '0', 3) > 0);
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null
            && bccomp($this->availableQty(), (string) $this->low_stock_threshold, 3) <= 0;
    }

    public function discountPercent(): int
    {
        return Money::discountPercent($this->price, $this->compare_at_price);
    }
}
