<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * پول. قیمت‌ها همیشه به افغانی ذخیره می‌شود؛ پول دیگر فقط برای نمایش با نرخ روز.
 *
 * @property string $code
 * @property string $symbol
 * @property string $rate_to_afn
 * @property int $decimals
 */
class Currency extends Model
{
    use HasTranslations;

    private const DEFAULT_CACHE_KEY = 'currencies.default';

    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'symbol', 'rate_to_afn', 'decimals', 'is_default', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'rate_to_afn' => 'decimal:6',
            'decimals' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::DEFAULT_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::DEFAULT_CACHE_KEY));
    }

    /** پول پیش‌فرض فروشگاه (افغانی). اگر دیتابیس آماده نبود null. */
    public static function defaultCurrency(): ?self
    {
        try {
            return Cache::rememberForever(
                self::DEFAULT_CACHE_KEY,
                fn (): ?self => self::query()->where('is_default', true)->first()
                    ?? self::query()->find(config('store.currency', 'AFN')),
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}
