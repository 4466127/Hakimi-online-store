<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Support\Persian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * صفحه‌ی ثابت فروشگاه: درباره‌ی ما، تماس، شرایط، مرجوعی، حریم خصوصی.
 */
class Page extends Model
{
    use HasFactory, HasTranslations, LogsActivity;

    public const FOOTER_CACHE_KEY = 'pages.footer';

    protected $fillable = ['slug', 'title', 'body', 'is_active', 'show_in_footer', 'position', 'meta_title', 'meta_description'];

    /** متن بلند صفحه در تاریخچه نوشته نمی‌شود (فقط اینکه عوض شد). */
    protected array $activityHidden = ['body'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // لیست صفحه‌های پاورقی cache می‌شود؛ با هر تغییر پاک شود
        static::saved(fn () => Cache::forget(self::FOOTER_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::FOOTER_CACHE_KEY));
    }

    /** آدرس همیشه به شکل خوانا و یک‌شکل ذخیره می‌شود. */
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Persian::slug($value),
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** آیا متن صفحه نوشته شده است؟ صفحه‌ی خالی به مشتری نشان داده نمی‌شود. */
    public function hasBody(): bool
    {
        return trim(strip_tags($this->tr('body'))) !== '';
    }

    public function url(): string
    {
        return route('page.show', ['slug' => $this->slug]);
    }

    public function activityLabel(): string
    {
        return $this->tr('title');
    }
}
