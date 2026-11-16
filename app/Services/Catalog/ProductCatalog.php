<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Persian;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * همه‌ی پرسش‌های «اجناسی که مشتری می‌بیند»: صفحه‌ی دسته، جستجو، صفحه‌ی اصلی، مرتبط.
 *
 * قاعده‌ی دیده شدن (یک جا): جنس فعال، زمان انتشارش رسیده، قیمتِ نوعِ فعال دارد، دسته‌اش و همه‌ی
 * بالادست‌های دسته فعال است، و یا مال خود بازارک است یا مال فروشنده‌ی تأییدشده.
 * جنس ناموجود می‌ماند ولی آخر لیست می‌آید (مثل دیجی‌کالا).
 */
class ProductCatalog
{
    public const PER_PAGE = 24;

    /**
     * شناسه‌ی دسته‌هایی که مشتری می‌بیند (خودش و همه‌ی بالادست‌هایش فعال)، اختیاری فقط زیر یک دسته.
     *
     * @return list<int>
     */
    public function visibleCategoryIds(?Category $within = null): array
    {
        $rows = Category::query()->where('is_active', true)->get(['id', 'path']);
        $active = $rows->pluck('id')->flip();

        $visible = $rows->filter(function (Category $category) use ($active, $within): bool {
            $ancestors = $category->ancestorIds();
            foreach ($ancestors as $id) {
                if (! $active->has($id)) {
                    return false;
                }
            }
            if ($within === null) {
                return true;
            }

            return $category->id === $within->id || in_array($within->id, $ancestors, true);
        });

        return $visible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** پایه‌ی همه‌ی لیست‌ها: دیده‌شدنی، اختیاری زیر یک دسته. */
    public function base(?Category $within = null): Builder
    {
        return Product::query()
            ->published()
            ->whereNotNull('products.min_price')
            ->whereIn('products.category_id', $this->visibleCategoryIds($within));
    }

    /** کلمه‌های جستجو: هر کلمه باید در متن جستجوی جنس باشد (نام، برند، دسته، SKU، مشخصات). */
    public function search(Builder $query, ListingFilters $filters): Builder
    {
        foreach ($filters->tokens as $token) {
            $query->where('products.search_text', 'like', '%'.$token.'%');
        }

        return $query;
    }

    public function apply(Builder $query, ListingFilters $filters): Builder
    {
        if ($filters->brandIds !== []) {
            $query->whereIn('products.brand_id', $filters->brandIds);
        }
        if ($filters->minPrice !== null) {
            $query->where('products.min_price', '>=', $filters->minPrice);
        }
        if ($filters->maxPrice !== null) {
            $query->where('products.min_price', '<=', $filters->maxPrice);
        }
        if ($filters->inStock) {
            $query->where('products.total_stock', '>', 0);
        }

        // هر مشخصه: یکی از گزینه‌های انتخاب‌شده، چه نوع‌ساز (سایز/رنگ) چه توصیفی (جنس رویه)
        foreach ($filters->options as $attributeId => $optionIds) {
            $query->where(function (Builder $q) use ($attributeId, $optionIds): void {
                $q->whereExists(
                    DB::table('product_variants as pv')
                        ->join('variant_attribute_values as vav', 'vav.variant_id', '=', 'pv.id')
                        ->selectRaw('1')
                        ->whereColumn('pv.product_id', 'products.id')
                        ->where('pv.is_active', true)
                        ->where('vav.attribute_id', $attributeId)
                        ->whereIn('vav.attribute_option_id', $optionIds)
                )->orWhereExists(
                    DB::table('product_attribute_values as pav')
                        ->selectRaw('1')
                        ->whereColumn('pav.product_id', 'products.id')
                        ->where('pav.attribute_id', $attributeId)
                        ->whereIn('pav.attribute_option_id', $optionIds)
                );
            });
        }

        return $query;
    }

    /** ناموجود آخر لیست؛ بعد مرتب‌سازی انتخابی. */
    public function sort(Builder $query, ListingFilters $filters): Builder
    {
        $query->orderByRaw('products.total_stock > 0 desc');

        match ($filters->sort) {
            'cheap' => $query->orderBy('products.min_price'),
            'expensive' => $query->orderByDesc('products.min_price'),
            'popular' => $query->orderByDesc('products.sold_count')->orderByDesc('products.views_count'),
            'relevance' => $query
                ->orderByRaw('products.search_text like ? desc', ['%'.implode(' ', $filters->tokens).'%'])
                ->orderByDesc('products.sold_count'),
            default => $query->orderByDesc('products.published_at'),
        };

        return $query->orderByDesc('products.id');
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(?Category $within, ListingFilters $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->base($within);
        if ($filters->hasSearch()) {
            $this->search($query, $filters);
        }
        $this->apply($query, $filters);
        $this->sort($query, $filters);

        return $query->with(['images', 'brand'])->paginate($perPage)->withQueryString();
    }

    /**
     * گزینه‌های فیلتر برای همین مجموعه (بی فیلترهای روشن): برندها، بازه‌ی قیمت، و مشخصه‌های فیلترپذیرِ دسته
     * فقط با گزینه‌هایی که واقعاً جنسی دارند.
     *
     * @return array{brands: Collection<int, Brand>, min: string|null, max: string|null, attributes: list<array{attribute: Attribute, options: Collection<int, AttributeOption>}>}
     */
    public function facets(?Category $within, ListingFilters $filters): array
    {
        $base = $this->base($within);
        if ($filters->hasSearch()) {
            $this->search($base, $filters);
        }

        $brands = Brand::query()->where('is_active', true)
            ->whereIn('id', (clone $base)->whereNotNull('products.brand_id')->select('products.brand_id'))
            ->orderBy('name')->get();

        $min = (clone $base)->min('products.min_price');
        $max = (clone $base)->max('products.min_price');

        $attributes = [];
        $filterable = $within?->effectiveAttributes()->where('is_filterable', true) ?? collect();
        if ($filterable->isNotEmpty()) {
            $ids = (clone $base)->select('products.id');

            $present = DB::table('variant_attribute_values as vav')
                ->join('product_variants as pv', 'pv.id', '=', 'vav.variant_id')
                ->where('pv.is_active', true)
                ->whereIn('pv.product_id', $ids)
                ->whereIn('vav.attribute_id', $filterable->pluck('id'))
                ->pluck('vav.attribute_option_id')
                ->merge(
                    DB::table('product_attribute_values as pav')
                        ->whereIn('pav.product_id', $ids)
                        ->whereIn('pav.attribute_id', $filterable->pluck('id'))
                        ->whereNotNull('pav.attribute_option_id')
                        ->pluck('pav.attribute_option_id')
                )
                ->unique();

            $options = AttributeOption::query()->whereIn('id', $present)->where('is_active', true)
                ->orderBy('position')->orderBy('id')->get()->groupBy('attribute_id');

            foreach ($filterable as $attribute) {
                $list = $options->get($attribute->id);
                if ($list !== null && $list->count() > 0) {
                    $attributes[] = ['attribute' => $attribute, 'options' => $list];
                }
            }
        }

        return ['brands' => $brands, 'min' => $min, 'max' => $max, 'attributes' => $attributes];
    }

    /**
     * دسته‌هایی که نامشان با جستجو می‌خورد (چند «چیپ» بالای نتیجه). نام‌ها در PHP و بعد از نرمال‌سازی
     * مقایسه می‌شود (ی/ي، ک/ك، ارقام)؛ تعداد دسته‌ها کم است.
     *
     * @return Collection<int, Category>
     */
    public function matchingCategories(ListingFilters $filters, int $limit = 6): Collection
    {
        if (! $filters->hasSearch()) {
            return collect();
        }

        return Category::query()->whereIn('id', $this->visibleCategoryIds())
            ->orderBy('depth')->orderBy('position')->get()
            ->filter(function (Category $category) use ($filters): bool {
                $name = Persian::normalize($category->tr('name'));
                foreach ($filters->tokens as $token) {
                    if (! str_contains($name, $token)) {
                        return false;
                    }
                }

                return true;
            })
            ->take($limit)->values();
    }

    /** جنس‌های تازه یا ویژه برای صفحه‌ی اصلی. @return Collection<int, Product> */
    public function home(bool $featured, int $limit = 8): Collection
    {
        $query = $this->base();
        if ($featured) {
            $query->where('products.is_featured', true);
        }
        $this->sort($query, new ListingFilters(sort: 'new'));

        return $query->with(['images', 'brand'])->limit($limit)->get();
    }

    /**
     * جنس مرتبط: اول آنچه مدیر انتخاب کرده، بعد جنس‌های همان دسته.
     *
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 8): Collection
    {
        $explicit = $this->base()
            ->whereIn('products.id', $product->relatedProducts()->pluck('products.id'))
            ->with(['images', 'brand'])->limit($limit)->get();

        if ($explicit->count() >= $limit) {
            return $explicit;
        }

        $similar = $this->base()
            ->where('products.category_id', $product->category_id)
            ->where('products.id', '!=', $product->id)
            ->whereNotIn('products.id', $explicit->pluck('id'))
            ->orderByRaw('products.total_stock > 0 desc')
            ->orderByDesc('products.sold_count')->orderByDesc('products.id')
            ->with(['images', 'brand'])->limit($limit - $explicit->count())->get();

        return $explicit->concat($similar)->values();
    }
}
