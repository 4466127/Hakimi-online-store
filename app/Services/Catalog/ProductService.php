<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\SellerStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Support\Money;
use App\Support\Num;
use App\Support\Persian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ساختن و ویرایش جنس با همه‌ی بخش‌هایش (نوع‌ها، مشخصات، جنس مرتبط) در یک transaction،
 * و ساختن cache روی جنس (کمترین/بیشترین قیمت، موجودی، متن جستجو).
 *
 * فورم پنل و (بعداً) پنل فروشنده و واردکردن گروهی همه فقط از همین‌جا می‌نویسند،
 * پس قاعده‌ها یک جا هست و تست می‌شود.
 *
 * ساختار $data:
 *   category_id، brand_id?، seller_id? (خالی = جنس خود بازارک)، name['fa']، short_description?،
 *   description?، meta_title?، meta_description?، slug? (خالی = از نام)، status، is_featured?،
 *   published_at?،
 *   variants: [ { id?, sku?, barcode?, price, compare_at_price?, cost_price?, stock_qty,
 *                 low_stock_threshold?, weight_grams?, is_active?, is_default?, option_ids[] } ]،
 *   attributes: { attribute_id: گزینه | [گزینه‌ها] | متن | عدد | بلی/نه }،
 *   related?: [ product_id ] (اگر نیاید، تغییر نمی‌کند).
 */
class ProductService
{
    /** سقف طول متن جستجو (ستون TEXT؛ بیشتر از این به جستجو کمکی نمی‌کند). */
    private const SEARCH_TEXT_LIMIT = 6000;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException با کلید فیلد (variants، slug، attributes، seller_id، …)
     */
    public function save(?Product $product, array $data, ?User $actor = null): Product
    {
        $category = Category::query()->find($data['category_id'] ?? null);
        if ($category === null) {
            throw $this->fail('category_id', 'category_missing');
        }

        $status = (string) ($data['status'] ?? 'draft');
        if (! in_array($status, Product::STATUSES, true)) {
            throw $this->fail('status', 'bad_status');
        }

        $sellerId = isset($data['seller_id']) && $data['seller_id'] !== '' ? (int) $data['seller_id'] : null;
        if ($sellerId !== null && ! Seller::query()->whereKey($sellerId)->where('status', SellerStatus::Approved->value)->exists()) {
            throw $this->fail('seller_id', 'seller_not_approved');
        }

        $variantAttributes = $category->effectiveAttributes()->where('is_variant', true)->keyBy('id');
        $descriptiveAttributes = $category->effectiveAttributes()->where('is_variant', false)->keyBy('id');

        $variants = $this->normalizeVariants($data['variants'] ?? [], $variantAttributes, $product, $status === 'active');
        $attributeRows = $this->normalizeAttributes($data['attributes'] ?? [], $descriptiveAttributes, $status === 'active');

        return DB::transaction(function () use ($product, $data, $category, $status, $sellerId, $variants, $attributeRows, $actor): Product {
            $product ??= new Product;
            $isNew = ! $product->exists;
            $oldSlug = $isNew ? null : $product->slug;

            $name = $this->textArray($data['name'] ?? []);
            if ($name === []) {
                throw $this->fail('name', 'name_required');
            }

            $product->fill([
                'category_id' => $category->id,
                'brand_id' => $this->brandId($data['brand_id'] ?? null),
                'seller_id' => $sellerId,
                'name' => $name,
                'short_description' => $this->textArray($data['short_description'] ?? []) ?: null,
                'description' => $this->textArray($data['description'] ?? []) ?: null,
                'meta_title' => $this->textArray($data['meta_title'] ?? []) ?: null,
                'meta_description' => $this->textArray($data['meta_description'] ?? []) ?: null,
                'status' => $status,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ]);
            $product->slug = $this->resolveSlug($product, $data['slug'] ?? null, $name);

            // تاریخ انتشار: اولین بار که فعال شد؛ مدیر می‌تواند برای آینده بگذارد
            if (! empty($data['published_at'])) {
                $product->published_at = $data['published_at'];
            } elseif ($status === 'active' && $product->published_at === null) {
                $product->published_at = now();
            }

            if ($isNew) {
                $product->created_by = $actor?->id;
            }
            $product->updated_by = $actor?->id;
            $product->save();

            $this->rememberOldSlug($product, $oldSlug);
            $this->syncVariants($product, $variants);
            $this->syncAttributes($product, $attributeRows);
            if (array_key_exists('related', $data)) {
                $this->syncRelated($product, (array) $data['related']);
            }

            return $this->refresh($product);
        });
    }

    /**
     * cache روی جنس را از نوع‌ها دوباره می‌سازد. هر وقت موجودی یا قیمت عوض شد
     * (فورم، سفارش در مرحله‌ی ۴) همین صدا زده می‌شود.
     */
    public function refresh(Product $product): Product
    {
        $variants = $product->variants()->with('options')->get();
        $active = $variants->where('is_active', true);

        $byPrice = $active->sortBy(fn (ProductVariant $v) => (float) $v->price)->values();
        $cheapest = $byPrice->first();
        $compare = $cheapest?->compare_at_price;

        $product->forceFill([
            'min_price' => $cheapest?->price,
            'max_price' => $byPrice->last()?->price,
            'min_compare_price' => $compare !== null && Money::compare($compare, $cheapest->price) > 0 ? $compare : null,
            'total_stock' => $active->reduce(fn (string $carry, ProductVariant $v) => bcadd($carry, $v->availableQty(), 3), '0.000'),
            'has_variants' => $variants->count() > 1 || $variants->contains(fn (ProductVariant $v) => $v->options->isNotEmpty()),
            'search_text' => $this->buildSearchText($product, $variants),
        ])->saveQuietly();

        return $product;
    }

    /**
     * فعال/غیرفعال کردن یک جنس (عمل گروهی پنل). جنس بی نوع فعال نمی‌تواند فعال شود (قیمت ندارد).
     *
     * @return bool false = فعال نشد چون نوع فعال ندارد
     */
    public function setStatus(Product $product, string $status): bool
    {
        if (! in_array($status, Product::STATUSES, true)) {
            throw $this->fail('status', 'bad_status');
        }
        if ($status === 'active' && ! $product->variants()->where('is_active', true)->exists()) {
            return false;
        }

        $product->status = $status;
        if ($status === 'active' && $product->published_at === null) {
            $product->published_at = now();
        }
        $product->save();

        return true;
    }

    // ───────────────────────── نوع‌ها ─────────────────────────

    /**
     * @param  Collection<int, Attribute>  $variantAttributes  مشخصه‌های نوع‌ساز این دسته
     * @return list<array<string, mixed>>
     */
    private function normalizeVariants(mixed $rows, Collection $variantAttributes, ?Product $product, bool $enforceRequired): array
    {
        $rows = array_values(array_filter((array) $rows, 'is_array'));
        if ($rows === []) {
            throw $this->fail('variants', 'no_variants');
        }

        $allowedOptions = AttributeOption::query()
            ->whereIn('attribute_id', $variantAttributes->keys())
            ->get(['id', 'attribute_id'])
            ->keyBy('id');

        $existingIds = $product?->exists ? $product->variants()->pluck('id')->all() : [];
        $seenCombos = [];
        $seenSkus = [];
        $out = [];

        foreach ($rows as $index => $row) {
            $price = $this->num($row['price'] ?? null);
            if ($price === null || Money::compare($price, 0) < 0) {
                throw $this->fail("variants.$index.price", 'price_required');
            }
            $compare = $this->num($row['compare_at_price'] ?? null);
            if ($compare !== null && Money::compare($compare, $price) <= 0) {
                throw $this->fail("variants.$index.compare_at_price", 'compare_price_low');
            }
            $stock = $this->num($row['stock_qty'] ?? 0) ?? '0';
            if (Money::compare($stock, 0) < 0) {
                throw $this->fail("variants.$index.stock_qty", 'stock_negative');
            }

            // گزینه‌ها: فقط از مشخصه‌های نوع‌سازِ این دسته، حداکثر یکی برای هر مشخصه
            $optionIds = [];
            $byAttribute = [];
            foreach ((array) ($row['option_ids'] ?? []) as $optionId) {
                $option = $allowedOptions->get((int) $optionId);
                if ($option === null) {
                    throw $this->fail("variants.$index.option_ids", 'option_not_allowed');
                }
                if (isset($byAttribute[$option->attribute_id])) {
                    throw $this->fail("variants.$index.option_ids", 'option_twice');
                }
                $byAttribute[$option->attribute_id] = $option->id;
                $optionIds[] = $option->id;
            }
            if ($enforceRequired) {
                foreach ($variantAttributes as $attribute) {
                    if ($attribute->pivot_required && ! isset($byAttribute[$attribute->id])) {
                        throw ValidationException::withMessages(["variants.$index.option_ids" => __('catalog.errors.option_required', ['attribute' => $attribute->tr('name')])]);
                    }
                }
            }
            sort($optionIds);

            $combo = implode('-', $optionIds);
            if (isset($seenCombos[$combo])) {
                throw $this->fail("variants.$index.option_ids", 'duplicate_combo');
            }
            $seenCombos[$combo] = true;

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            if ($id !== null && ! in_array($id, $existingIds, true)) {
                $id = null;   // نوعی که مال این جنس نیست، نوِ تازه حساب می‌شود
            }

            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $sku = mb_strtoupper(Num::toEn($sku), 'UTF-8');
                if (isset($seenSkus[$sku])
                    || ProductVariant::query()->where('sku', $sku)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    throw $this->fail("variants.$index.sku", 'sku_taken');
                }
                $seenSkus[$sku] = true;
            }

            $out[] = [
                'id' => $id,
                'sku' => $sku,
                'barcode' => $this->nullIfBlank($row['barcode'] ?? null),
                'price' => $price,
                'compare_at_price' => $compare,
                'cost_price' => $this->num($row['cost_price'] ?? null),
                'stock_qty' => $stock,
                'low_stock_threshold' => $this->num($row['low_stock_threshold'] ?? null),
                'weight_grams' => isset($row['weight_grams']) && $row['weight_grams'] !== '' ? max(0, (int) $row['weight_grams']) : null,
                'allow_backorder' => (bool) ($row['allow_backorder'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'is_default' => (bool) ($row['is_default'] ?? false),
                'option_ids' => $optionIds,
            ];
        }

        return $out;
    }

    /** @param  list<array<string, mixed>>  $variants */
    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];
        $hasDefault = false;

        foreach ($variants as $position => $row) {
            $options = AttributeOption::query()->with('attribute')->whereIn('id', $row['option_ids'])->get()
                ->sortBy(fn (AttributeOption $o) => [$o->attribute->position, $o->attribute_id]);

            $attributes = [
                'product_id' => $product->id,
                'barcode' => $row['barcode'],
                'label' => $options->isEmpty() ? null : mb_substr($options->map(fn (AttributeOption $o) => $o->tr('value'))->implode(' / '), 0, 100),
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'],
                'cost_price' => $row['cost_price'],
                'stock_qty' => $row['stock_qty'],
                'low_stock_threshold' => $row['low_stock_threshold'],
                'weight_grams' => $row['weight_grams'],
                'allow_backorder' => $row['allow_backorder'],
                'is_active' => $row['is_active'],
                'position' => $position,
            ];

            $isDefault = $row['is_default'] && $row['is_active'] && ! $hasDefault;
            $hasDefault = $hasDefault || $isDefault;

            if ($row['id'] !== null) {
                $variant = $product->variants()->findOrFail($row['id']);
                $variant->fill($attributes + ['is_default' => $isDefault]);
                if ($row['sku'] !== '') {
                    $variant->sku = $row['sku'];
                }
                $variant->save();
            } else {
                $variant = new ProductVariant($attributes + ['is_default' => $isDefault, 'sku' => $row['sku'] !== '' ? $row['sku'] : $this->makeSku($product, $position)]);
                $variant->save();
            }
            $keptIds[] = $variant->id;

            // گزینه‌ها از نو نوشته می‌شود
            $variant->optionValues()->delete();
            foreach ($options as $option) {
                $variant->optionValues()->create(['attribute_id' => $option->attribute_id, 'attribute_option_id' => $option->id]);
            }
        }

        // نوعی که از فورم برداشته شده پاک شود
        $product->variants()->whereNotIn('id', $keptIds)->get()->each->delete();

        // همیشه یک نوع پیش‌فرض: اولین نوع فعال (اگر هیچ‌کدام نشده بود)
        if (! $hasDefault) {
            $first = $product->variants()->where('is_active', true)->first() ?? $product->variants()->first();
            $first?->forceFill(['is_default' => true])->save();
        }
    }

    /** SKU خودکار و یکتا: BZ-000123-1 ، BZ-000123-2 … */
    private function makeSku(Product $product, int $position): string
    {
        $n = $position + 1;
        do {
            $sku = sprintf('BZ-%06d-%d', $product->id, $n++);
        } while (ProductVariant::query()->where('sku', $sku)->exists());

        return $sku;
    }

    // ───────────────────────── مشخصات توصیفی ─────────────────────────

    /**
     * @param  Collection<int, Attribute>  $attributes  مشخصه‌های غیرنوع‌ساز این دسته
     * @return list<array<string, mixed>> ردیف‌های آماده‌ی product_attribute_values
     */
    private function normalizeAttributes(mixed $values, Collection $attributes, bool $enforceRequired): array
    {
        $values = (array) $values;
        $rows = [];

        foreach ($attributes as $attribute) {
            $raw = $values[$attribute->id] ?? null;
            $empty = $raw === null || $raw === '' || $raw === [];

            if ($empty) {
                if ($enforceRequired && $attribute->pivot_required) {
                    throw ValidationException::withMessages(['attributes' => __('catalog.errors.attribute_required', ['attribute' => $attribute->tr('name')])]);
                }

                continue;
            }

            switch ($attribute->type) {
                case 'select':
                case 'multiselect':
                    $ids = array_map('intval', (array) $raw);
                    $valid = AttributeOption::query()->where('attribute_id', $attribute->id)->whereIn('id', $ids)->pluck('id')->all();
                    if (count($valid) !== count(array_unique($ids))) {
                        throw ValidationException::withMessages(['attributes' => __('catalog.errors.option_not_allowed')]);
                    }
                    if ($attribute->type === 'select') {
                        $valid = array_slice($valid, 0, 1);
                    }
                    foreach ($valid as $id) {
                        $rows[] = ['attribute_id' => $attribute->id, 'attribute_option_id' => $id];
                    }
                    break;
                case 'number':
                    $number = $this->num($raw);
                    if ($number === null) {
                        throw ValidationException::withMessages(['attributes' => __('catalog.errors.attribute_number', ['attribute' => $attribute->tr('name')])]);
                    }
                    $rows[] = ['attribute_id' => $attribute->id, 'value_number' => $number];
                    break;
                case 'boolean':
                    $rows[] = ['attribute_id' => $attribute->id, 'value_bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN)];
                    break;
                default:
                    $rows[] = ['attribute_id' => $attribute->id, 'value_text' => mb_substr(trim((string) $raw), 0, 255)];
            }
        }

        return $rows;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function syncAttributes(Product $product, array $rows): void
    {
        $product->attributeValues()->delete();
        foreach ($rows as $row) {
            $product->attributeValues()->create($row);
        }
    }

    /** @param  list<int|string>  $ids */
    private function syncRelated(Product $product, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id !== $product->id)->unique()->values();
        $valid = Product::query()->whereIn('id', $ids)->pluck('id')->all();

        $sync = [];
        foreach ($ids->filter(fn (int $id) => in_array($id, $valid, true))->values() as $position => $id) {
            $sync[$id] = ['kind' => 'related', 'position' => $position];
        }
        $product->relatedProducts()->sync($sync);
    }

    // ───────────────────────── آدرس (slug) ─────────────────────────

    /**
     * آدرس جنس: اگر مدیر نوشته همان (و اگر گرفته شده بود خطا)؛ وگرنه از نام ساخته و در صورت تکراری
     * «-۲»، «-۳»… می‌گیرد. جنسِ ویرایش‌شده که آدرس‌اش را عوض نکرده، همان را نگه می‌دارد.
     *
     * @param  array<string, string>  $name
     */
    private function resolveSlug(Product $product, mixed $typed, array $name): string
    {
        $typed = is_string($typed) ? trim($typed) : '';

        if ($typed !== '') {
            $slug = Persian::slug($typed, 200);
            if ($slug === '' || $this->slugTaken($slug, $product)) {
                throw $this->fail('slug', $slug === '' ? 'slug_empty' : 'slug_taken');
            }

            return $slug;
        }

        // آدرس نوشته نشد: جنس موجود آدرس فعلی‌اش را نگه می‌دارد (نام عوض شد، لینک نمی‌شکند)
        if ($product->exists && $product->slug !== '') {
            return $product->slug;
        }

        $base = Persian::slug($name['fa'] ?? (string) reset($name), 190) ?: 'product';
        $slug = $base;
        for ($n = 2; $this->slugTaken($slug, $product); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private function slugTaken(string $slug, Product $product): bool
    {
        return Product::withTrashed()->where('slug', $slug)
            ->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))
            ->exists();
    }

    /** آدرس قدیمی را ریدایرکت می‌کند و ریدایرکتی که با آدرس زنده‌ی تازه یکی است برمی‌دارد. */
    private function rememberOldSlug(Product $product, ?string $oldSlug): void
    {
        SlugRedirect::query()->where('type', 'product')->where('old_slug', $product->slug)->delete();

        if ($oldSlug !== null && $oldSlug !== $product->slug) {
            SlugRedirect::query()->updateOrCreate(
                ['type' => 'product', 'old_slug' => $oldSlug],
                ['target_id' => $product->id],
            );
        }
    }

    // ───────────────────────── جستجو ─────────────────────────

    /** @param  Collection<int, ProductVariant>  $variants */
    private function buildSearchText(Product $product, Collection $variants): string
    {
        $parts = [...array_values((array) $product->name)];

        if ($product->brand_id !== null) {
            $parts[] = (string) Brand::withTrashed()->whereKey($product->brand_id)->value('name');
        }

        $category = Category::withTrashed()->find($product->category_id);
        if ($category !== null) {
            foreach ([...$category->ancestors(), $category] as $node) {
                $parts[] = $node->tr('name');
            }
        }

        foreach ([$product->short_description, $product->description] as $text) {
            foreach ((array) $text as $value) {
                $parts[] = strip_tags((string) $value);
            }
        }

        foreach ($variants as $variant) {
            array_push($parts, (string) $variant->label, $variant->sku, (string) $variant->barcode);
        }

        $rows = $product->attributeValues()->with('option')->get();
        foreach ($rows as $row) {
            if ($row->option !== null) {
                $parts[] = $row->option->tr('value');
            } elseif ($row->value_text !== null) {
                $parts[] = $row->value_text;
            }
        }

        return mb_substr(Persian::normalize(implode(' ', array_filter($parts, fn ($p) => $p !== ''))), 0, self::SEARCH_TEXT_LIMIT);
    }

    // ───────────────────────── کمکی ─────────────────────────

    /** @return array<string, string> فقط زبان‌هایی که متن دارند. */
    private function textArray(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $out[(string) $locale] = $text;
            }
        }

        return $out;
    }

    private function brandId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        return Brand::query()->whereKey((int) $id)->exists() ? (int) $id : null;
    }

    /** عدد نوشته‌شده (فارسی یا انگلیسی، با ویرگول) ← رشته‌ی استاندارد یا null. */
    private function num(mixed $value): ?string
    {
        return is_scalar($value) ? Num::parse((string) $value) : null;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fail(string $field, string $messageKey): ValidationException
    {
        return ValidationException::withMessages([$field => __('catalog.errors.'.$messageKey)]);
    }
}
