<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Services\ImageStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * بنر صفحه‌ی اصلی (مرحله‌ی ۹-۴). حداکثر ۵ بنر روشن؛ ترتیب با `position`.
 *
 * @property int $id
 * @property array<string, string>|null $title
 * @property string $image_path
 * @property string|null $link_url
 * @property int $position
 * @property bool $is_active
 */
class Banner extends Model
{
    use HasTranslations, LogsActivity;

    public const MAX_ACTIVE = 5;

    protected $fillable = ['title', 'image_path', 'link_url', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // سقف ۵ بنر روشن؛ فورم پیام دری می‌دهد، این‌جا آخرین محافظت است
        static::saving(function (Banner $banner): void {
            if ($banner->is_active && ($banner->isDirty('is_active') || ! $banner->exists)
                && self::activeCountExcept($banner->id) >= self::MAX_ACTIVE) {
                throw new LogicException('At most '.self::MAX_ACTIVE.' banners can be active.');
            }
        });
        // عکس قدیمی پاک شود (عکس عوض شد یا بنر حذف شد)
        static::updated(function (Banner $banner): void {
            if ($banner->wasChanged('image_path')) {
                app(ImageStore::class)->delete((string) $banner->getOriginal('image_path'));
            }
        });
        static::deleted(fn (Banner $banner) => app(ImageStore::class)->delete($banner->image_path));
    }

    public static function activeCountExcept(?int $id): int
    {
        return self::query()->where('is_active', true)->when($id !== null, fn (Builder $q) => $q->whereKeyNot($id))->count();
    }

    /** بنرهای روشن صفحه‌ی اصلی به ترتیب (حداکثر ۵). */
    public function scopeForHome(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('id')->limit(self::MAX_ACTIVE);
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    /** لینک بیرونی؟ (برای rel="noopener") */
    public function isExternal(): bool
    {
        $host = parse_url((string) $this->link_url, PHP_URL_HOST);

        return $host !== null && $host !== false && $host !== parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    public function activityLabel(): string
    {
        return $this->tr('title') ?: '#'.$this->id;
    }
}
