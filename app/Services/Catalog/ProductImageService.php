<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * عکس‌های جنس: هر عکس به WebP در سه اندازه (۳۰۰، ۶۰۰، ۱۲۰۰) تبدیل می‌شود.
 * عکس اول = عکس اصلی (کارت و صفحه‌ی جنس). اصل عکس نگه داشته نمی‌شود.
 */
class ProductImageService
{
    public const MAX_IMAGES = 10;

    public function __construct(private readonly ImageStore $images) {}

    /**
     * یک عکس بارگذاری‌شده را به جنس اضافه می‌کند (آخر لیست).
     *
     * @throws ValidationException اگر عکس درست نبود یا جنس به سقف عکس رسیده بود
     */
    public function add(Product $product, string $sourcePath, ?string $alt = null): ProductImage
    {
        if ($product->images()->count() >= self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => __('catalog.errors.too_many_images', ['max' => self::MAX_IMAGES])]);
        }

        try {
            $stored = $this->images->storeResponsive($sourcePath, 'products/'.$product->uuid);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['images' => __('catalog.errors.bad_image')]);
        }

        $position = (int) $product->images()->reorder()->max('position') + 1;

        return $product->images()->create([
            'path' => $stored['path'],
            'sizes' => $stored['sizes'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'file_size' => $stored['file_size'],
            'alt' => $alt !== null && $alt !== '' ? ['fa' => $alt] : null,
            'position' => $position,
            'is_primary' => ! $product->images()->where('is_primary', true)->exists(),
        ]);
    }

    /**
     * ترتیب و متن توضیح عکس‌های موجود + عکس‌های تازه را یک‌جا درست می‌کند.
     * عکسی که در $keep نیامد پاک می‌شود. عکس اول لیست = عکس اصلی.
     *
     * @param  list<array{id: int, alt?: string|null}>  $keep  عکس‌های فعلی به ترتیب دلخواه
     * @param  list<string>  $newFiles  مسیر فایل‌های بارگذاری‌شده‌ی تازه
     */
    public function sync(Product $product, array $keep, array $newFiles = []): void
    {
        $keepIds = array_map(fn (array $row) => (int) $row['id'], $keep);

        if (count($keepIds) + count($newFiles) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => __('catalog.errors.too_many_images', ['max' => self::MAX_IMAGES])]);
        }

        DB::transaction(function () use ($product, $keep, $keepIds, $newFiles): void {
            foreach ($product->images()->whereNotIn('id', $keepIds)->get() as $image) {
                $this->remove($image);
            }

            foreach ($keep as $row) {
                $alt = $row['alt'] ?? null;
                $product->images()->whereKey((int) $row['id'])->update([
                    'alt' => $alt !== null && $alt !== '' ? json_encode(['fa' => $alt], JSON_UNESCAPED_UNICODE) : null,
                ]);
            }

            // ترتیب فعلی (به ترتیب $keep)، بعد تازه‌ها
            $order = $keepIds;
            foreach ($newFiles as $path) {
                $order[] = $this->add($product, $path)->id;
            }

            $this->applyOrder($product, $order);
        });
    }

    /** یک عکس را با همه‌ی نسخه‌هایش پاک می‌کند؛ اگر اصلی بود، بعدی اصلی می‌شود. */
    public function remove(ProductImage $image): void
    {
        $product = Product::withTrashed()->find($image->product_id);
        $files = $image->files();
        $image->delete();

        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }

        if ($product !== null && ! $product->images()->where('is_primary', true)->exists()) {
            $this->applyOrder($product, $product->images()->pluck('id')->all());
        }
    }

    /** @param  list<int>  $ids  شناسه‌ی عکس‌ها به ترتیب نهایی */
    private function applyOrder(Product $product, array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            $product->images()->whereKey($id)->update(['position' => $index, 'is_primary' => $index === 0]);
        }
    }
}
