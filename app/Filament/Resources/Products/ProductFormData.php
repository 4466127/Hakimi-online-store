<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductImageService;
use App\Services\Catalog\ProductService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * پل بین فورم پنل و ProductService: داده‌ی جنس موجود را برای فورم می‌سازد، و داده‌ی فورم را
 * (نوع‌ها، مشخصات، عکس‌ها) با قاعده‌های سرویس ذخیره می‌کند. منطق کار در سرویس‌هاست، نه اینجا.
 */
class ProductFormData
{
    /** @return array<string, mixed> داده‌های اضافه برای پر کردن فورم ویرایش */
    public static function forForm(Product $product): array
    {
        $product->load(['images', 'variants.options', 'attributeValues.attribute', 'relatedProducts']);

        $specs = [];
        foreach ($product->attributeValues->groupBy('attribute_id') as $attributeId => $rows) {
            $first = $rows->first();
            $specs[$attributeId] = match (true) {
                $first->attribute_option_id !== null => $rows->count() > 1 || $first->attribute?->type === 'multiselect'
                    ? $rows->pluck('attribute_option_id')->all()
                    : $first->attribute_option_id,
                $first->value_number !== null => rtrim(rtrim((string) $first->value_number, '0'), '.'),
                $first->value_bool !== null => (bool) $first->value_bool,
                default => (string) $first->value_text,
            };
        }

        return [
            'existing_images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'alt' => $image->tr('alt'),
                'url' => $image->url(300),
            ])->values()->all(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'option_ids' => $v->options->pluck('id')->all(),
                'price' => self::plain($v->price),
                'compare_at_price' => self::plain($v->compare_at_price),
                'cost_price' => self::plain($v->cost_price),
                'stock_qty' => self::plain($v->stock_qty),
                'low_stock_threshold' => self::plain($v->low_stock_threshold),
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'weight_grams' => $v->weight_grams,
                'is_active' => $v->is_active,
                'is_default' => $v->is_default,
                'allow_backorder' => $v->allow_backorder,
            ])->values()->all(),
            'specs' => $specs,
            'related' => $product->relatedProducts->pluck('id')->all(),
            'new_images' => [],
        ];
    }

    /**
     * فورم را در پایگاه داده می‌نویسد (جنس، نوع‌ها، مشخصات، عکس‌ها) در یک transaction جدا برای هر بخش.
     * خطاهای قاعده‌ها به فیلد همان فورم (`data.…`) وصل می‌شود.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persist(?Product $product, array $data): Product
    {
        $newFiles = [];
        foreach ((array) ($data['new_images'] ?? []) as $relative) {
            if (is_string($relative) && Storage::disk('local')->exists($relative)) {
                $newFiles[] = Storage::disk('local')->path($relative);
            }
        }

        try {
            $saved = app(ProductService::class)->save($product, self::serviceData($data), auth()->user());
            app(ProductImageService::class)->sync($saved, self::keptImages($data), $newFiles);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(self::prefixed($e->errors(), array_keys((array) ($data['variants'] ?? []))));
        } finally {
            // فایل‌های موقت بارگذاری هر طور شد پاک شود
            foreach ((array) ($data['new_images'] ?? []) as $relative) {
                if (is_string($relative)) {
                    Storage::disk('local')->delete($relative);
                }
            }
        }

        return $saved->refresh();
    }

    /** @param  array<string, mixed>  $data */
    private static function serviceData(array $data): array
    {
        $out = $data;
        $out['attributes'] = (array) ($data['specs'] ?? []);
        $out['variants'] = array_values((array) ($data['variants'] ?? []));
        if (! array_key_exists('related', $data)) {
            unset($out['related']);
        }
        if (empty($data['published_at'])) {
            unset($out['published_at']);
        }

        return $out;
    }

    /** @return list<array{id: int, alt: string|null}> */
    private static function keptImages(array $data): array
    {
        return collect((array) ($data['existing_images'] ?? []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['id']))
            ->map(fn (array $row) => ['id' => (int) $row['id'], 'alt' => isset($row['alt']) ? trim((string) $row['alt']) : null])
            ->values()
            ->all();
    }

    /**
     * خطای سرویس («variants.2.price») ← فیلد فورم («data.variants.<کلید ردیف>.price»).
     *
     * @param  array<string, list<string>>  $errors
     * @param  list<int|string>  $variantKeys  کلید ردیف‌های تکرارشونده‌ی فورم به ترتیب
     * @return array<string, list<string>>
     */
    private static function prefixed(array $errors, array $variantKeys): array
    {
        $out = [];
        foreach ($errors as $key => $messages) {
            if ($key === 'attributes') {
                $key = 'specs';
            } elseif (preg_match('/^variants\.(\d+)\.(.+)$/', $key, $m) && isset($variantKeys[(int) $m[1]])) {
                $key = 'variants.'.$variantKeys[(int) $m[1]].'.'.$m[2];
            }
            $out['data.'.$key] = $messages;
        }

        return $out;
    }

    private static function plain(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string) $value;

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
