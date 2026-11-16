<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * عکس جنس در چند اندازه WebP (۳۰۰، ۶۰۰، ۱۲۰۰). موبایل نسخه‌ی کوچک را می‌گیرد (srcset).
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property array<int|string, string> $sizes پهنا ← مسیر فایل
 * @property bool $is_primary
 */
class ProductImage extends Model
{
    use HasTranslations;

    protected $fillable = ['product_id', 'path', 'alt', 'position', 'is_primary', 'width', 'height', 'sizes', 'file_size'];

    protected function casts(): array
    {
        return [
            'alt' => 'array',
            'sizes' => 'array',
            'is_primary' => 'boolean',
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductImage $image): void {
            $image->uuid ??= (string) Str::uuid();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** پهنای نسخه‌ها، از کوچک به بزرگ. @return list<int> */
    public function widths(): array
    {
        $widths = array_map('intval', array_keys($this->sizes ?? []));
        sort($widths);

        return $widths;
    }

    /** آدرس کوچک‌ترین نسخه‌ای که حداقل $width پهنا دارد (اگر نبود، بزرگ‌ترین). */
    public function url(int $width = 600): string
    {
        $widths = $this->widths();
        if ($widths === []) {
            return Storage::disk('public')->url($this->path);
        }
        $chosen = collect($widths)->first(fn (int $w) => $w >= $width) ?? end($widths);

        return Storage::disk('public')->url($this->sizes[(string) $chosen] ?? $this->sizes[$chosen] ?? $this->path);
    }

    /** مقدار srcset: «…-300.webp 300w, …-600.webp 600w». */
    public function srcset(): string
    {
        return collect($this->widths())
            ->map(fn (int $w) => Storage::disk('public')->url($this->sizes[(string) $w]).' '.$w.'w')
            ->implode(', ');
    }

    /** مسیر همه‌ی فایل‌های این عکس در دیسک (برای پاک کردن). @return list<string> */
    public function files(): array
    {
        return array_values(array_unique([...array_values($this->sizes ?? []), $this->path]));
    }

    public function altText(): string
    {
        $alt = $this->tr('alt');

        return $alt !== '' ? $alt : $this->product?->tr('name') ?? '';
    }
}
