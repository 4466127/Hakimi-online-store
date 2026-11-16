<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Support\Persian;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * جنس. هر جنس حداقل یک نوع (ProductVariant) دارد؛ قیمت، موجودی و SKU روی نوع است.
 *
 * - seller_id خالی = جنس خود بازارک (کمیسیون ندارد)؛ پر = جنس فروشنده.
 * - min_price، max_price، total_stock، has_variants و search_text «cache» هستند و فقط
 *   با `App\Services\Catalog\ProductService` ساخته می‌شوند، نه دستی.
 *
 * @property int $id
 * @property string $uuid
 * @property int $category_id
 * @property int|null $brand_id
 * @property int|null $seller_id
 * @property string $slug
 * @property string $status
 * @property bool $is_featured
 * @property bool $has_variants
 * @property string|null $min_price
 * @property string|null $max_price
 * @property string $total_stock
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive'];

    public const STATUS_COLORS = ['draft' => 'gray', 'active' => 'success', 'inactive' => 'danger'];

    /** ستون‌هایی که ساختن/تغییرشان در تاریخچه‌ی فعالیت نوشته نشود (cache و شمارنده). */
    protected array $activityHidden = ['search_text', 'min_price', 'max_price', 'min_compare_price', 'total_stock', 'has_variants', 'views_count', 'sold_count'];

    protected $fillable = [
        'category_id', 'brand_id', 'seller_id', 'slug', 'name', 'short_description', 'description',
        'status', 'is_featured', 'meta_title', 'meta_description', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'short_description' => 'array',
            'description' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_featured' => 'boolean',
            'has_variants' => 'boolean',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'min_compare_price' => 'decimal:2',
            'total_stock' => 'decimal:3',
            'views_count' => 'integer',
            'sold_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->uuid ??= (string) Str::uuid();
            $product->search_text ??= '';
        });
    }

    /** آدرس همیشه خوانا و یک‌شکل (حروف فارسی می‌ماند). */
    protected function slug(): CastAttribute
    {
        return CastAttribute::make(set: fn (?string $value) => Persian::slug($value, 200));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /** خالی = جنس خود بازارک. */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** جنس مرتبط و لوازم (pivot: kind، position). */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->withPivot(['kind', 'position'])
            ->orderByPivot('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * جنسی که مشتری می‌تواند ببیند: فعال، تاریخ انتشار رسیده، و یا مال خود بازارک یا مال فروشنده‌ی تأییدشده.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('products.status', 'active')
            ->whereNotNull('products.published_at')
            ->where('products.published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('products.seller_id')
                ->orWhereHas('seller', fn (Builder $s) => $s->where('status', SellerStatus::Approved->value)));
    }

    public function scopeOfSeller(Builder $query, ?int $sellerId): Builder
    {
        return $sellerId === null ? $query->whereNull('seller_id') : $query->where('seller_id', $sellerId);
    }

    public function isOwnStore(): bool
    {
        return $this->seller_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInStock(): bool
    {
        return bccomp((string) $this->total_stock, '0', 3) > 0;
    }

    public function url(): string
    {
        return route('product.show', ['slug' => $this->slug]);
    }

    public function primaryImage(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_primary', true) ?? $images->first();
    }

    public function statusLabel(): string
    {
        return (string) __('admin.products.statuses.'.$this->status);
    }

    public function activityLabel(): string
    {
        return $this->tr('name');
    }
}
