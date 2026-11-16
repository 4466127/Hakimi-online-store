<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ولایت افغانستان (۳۴ ولایت؛ داده‌ی اولیه از ProvinceSeeder).
 *
 * delivery_enabled: آیا فعلاً به این ولایت جنس می‌رسانیم. در شروع کابل، از ۲۶ عقرب ۱۴۰۵ فقط کابل؛
 * برای بقیه در سایت «زیر کار است» نوشته می‌شود.
 *
 * @property string $code
 * @property bool $delivery_enabled
 */
class Province extends Model
{
    use HasTranslations, LogsActivity;

    public const DELIVERY_CACHE_KEY = 'provinces.delivery';

    public $timestamps = false;

    protected $fillable = ['code', 'name', 'position', 'is_active', 'delivery_enabled'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
            'delivery_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::DELIVERY_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::DELIVERY_CACHE_KEY));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    /** ولایت‌هایی که فعلاً تحویل در آن‌ها فعال است. */
    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('delivery_enabled', true)->orderBy('position');
    }

    /**
     * نام ولایت‌هایی که تحویل دارند، برای نمایش در سایت (cache می‌شود).
     *
     * @return list<string>
     */
    public static function deliverableNames(): array
    {
        try {
            return Cache::rememberForever(
                self::DELIVERY_CACHE_KEY,
                fn (): array => self::query()->deliverable()->get()
                    ->map(fn (self $province): string => $province->tr('name'))->values()->all(),
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public function activityLabel(): string
    {
        return $this->tr('name');
    }
}
