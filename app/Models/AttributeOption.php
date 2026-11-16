<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Persian;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک گزینه‌ی مشخصه: «۴۲»، «سیاه» (با رنگ #000000)، «۱۲۸ GB»، «نو»، …
 *
 * @property int $attribute_id
 * @property string $slug
 * @property string|null $color_hex
 */
class AttributeOption extends Model
{
    use HasTranslations;

    protected $fillable = ['attribute_id', 'slug', 'value', 'color_hex', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // آدرس گزینه اگر داده نشد از خود مقدار ساخته شود
        static::saving(function (AttributeOption $option): void {
            if (blank($option->slug)) {
                $option->slug = Persian::slug($option->tr('value'));
            }
        });
    }

    protected function slug(): CastAttribute
    {
        return CastAttribute::make(set: fn (?string $value) => $value === null ? null : Persian::slug($value, 100));
    }

    protected function colorHex(): CastAttribute
    {
        return CastAttribute::make(set: fn (?string $value) => $value ? strtolower($value) : null);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
