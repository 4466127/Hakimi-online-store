<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Services\Catalog\ProductCatalog;
use App\Services\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * ویدیوی صفحه‌ی اصلی (خواست صاحب پروژه، ۵ قوس ۱۴۰۵؛ الگو: ویدیوی جنس دیجی‌کالا).
 * ویدیوی کوتاه از خود سرور + عکس پیش‌نمایش WebP + چند جنس زیرش. حداکثر ۳ ویدیوی روشن.
 *
 * @property int $id
 * @property array<string, string> $title
 * @property string $video_path
 * @property string $poster_path
 * @property string|null $link_url
 * @property array<int, int>|null $product_ids
 * @property int $position
 * @property bool $is_active
 */
class HomeVideo extends Model
{
    use HasTranslations, LogsActivity;

    public const MAX_ACTIVE = 3;

    public const MAX_PRODUCTS = 8;

    /** بیشترین حجم ویدیو (کیلوبایت). انترنت افغانستان ضعیف است؛ ویدیوی کوتاه و سبک. */
    public const MAX_VIDEO_KB = 8192;

    protected $fillable = ['title', 'video_path', 'poster_path', 'link_url', 'product_ids', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'product_ids' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // سقف ویدیوهای روشن؛ فورم پیام دری می‌دهد، این‌جا آخرین محافظت است
        static::saving(function (HomeVideo $video): void {
            if ($video->is_active && ($video->isDirty('is_active') || ! $video->exists)
                && self::activeCountExcept($video->id) >= self::MAX_ACTIVE) {
                throw new LogicException('At most '.self::MAX_ACTIVE.' home videos can be active.');
            }
            // فقط عددهای یکتا، حداکثر ۸
            $ids = array_values(array_unique(array_map('intval', (array) ($video->product_ids ?? []))));
            $video->product_ids = array_slice(array_filter($ids, fn (int $id): bool => $id > 0), 0, self::MAX_PRODUCTS) ?: null;
        });
        // فایل قدیمی پاک شود (عوض شد یا ویدیو حذف شد)
        static::updated(function (HomeVideo $video): void {
            if ($video->wasChanged('video_path')) {
                Storage::disk('public')->delete((string) $video->getOriginal('video_path'));
            }
            if ($video->wasChanged('poster_path')) {
                app(ImageStore::class)->delete((string) $video->getOriginal('poster_path'));
            }
        });
        static::deleted(function (HomeVideo $video): void {
            Storage::disk('public')->delete($video->video_path);
            app(ImageStore::class)->delete($video->poster_path);
        });
    }

    public static function activeCountExcept(?int $id): int
    {
        return self::query()->where('is_active', true)->when($id !== null, fn (Builder $q) => $q->whereKeyNot($id))->count();
    }

    /** ویدیوهای روشن صفحه‌ی اصلی به ترتیب. */
    public function scopeForHome(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('id')->limit(self::MAX_ACTIVE);
    }

    public function videoUrl(): string
    {
        return Storage::disk('public')->url($this->video_path);
    }

    public function posterUrl(): string
    {
        return Storage::disk('public')->url($this->poster_path);
    }

    /** نوع فایل برای <source type>. */
    public function videoMime(): string
    {
        return str_ends_with(strtolower($this->video_path), '.webm') ? 'video/webm' : 'video/mp4';
    }

    /**
     * اجناس فعال زیر ویدیو، به همان ترتیبی که مدیر چید (جنس خاموش یا حذف‌شده نشان داده نمی‌شود).
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        $ids = (array) ($this->product_ids ?? []);
        if ($ids === []) {
            return new Collection;
        }

        return app(ProductCatalog::class)->base()->whereIn('products.id', $ids)->with(['images', 'brand'])->get()
            ->sortBy(fn (Product $p): int => (int) array_search($p->id, $ids, true))
            ->values();
    }

    /** لینک بیرونی؟ (برای rel="noopener") */
    public function isExternal(): bool
    {
        $host = parse_url((string) $this->link_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' && $host !== request()->getHost();
    }

    public function activityLabel(): string
    {
        return $this->tr('title');
    }
}
