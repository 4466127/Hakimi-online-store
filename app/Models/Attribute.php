<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مشخصه‌ی کالا: سایز، رنگ، جنس، RAM، حافظه، پروسسور، گارانتی، وضعیت (نو/کارکرده)، …
 *
 * - type: select (یک گزینه) / multiselect (چند گزینه) / text / number / boolean
 * - is_variant: نوع‌ساز است (هر سایز/رنگ/حافظه قیمت و موجودی جدا دارد) — فقط select
 * - is_filterable: در فیلتر صفحه‌ی دسته نشان داده شود
 * - show_in_specs: در جدول مشخصات صفحه‌ی جنس
 *
 * داده است، نه کد: کد چیزی درباره‌ی «کفش» یا «موبایل» نمی‌داند.
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property bool $is_variant
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    public const TYPES = ['select', 'multiselect', 'text', 'number', 'boolean'];

    /** نوع‌هایی که گزینه دارند. */
    public const OPTION_TYPES = ['select', 'multiselect'];

    protected $fillable = ['code', 'name', 'type', 'unit', 'is_variant', 'is_filterable', 'show_in_specs', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_variant' => 'boolean',
            'is_filterable' => 'boolean',
            'show_in_specs' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // فقط مشخصه‌ی تک‌گزینه‌ای می‌تواند نوع‌ساز باشد (سایز ۴۲، رنگ سیاه)
        static::saving(function (Attribute $attribute): void {
            if ($attribute->type !== 'select') {
                $attribute->is_variant = false;
            }
        });
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('position')->orderBy('id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute')->withPivot(['is_required', 'position']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }

    public function typeLabel(): string
    {
        return (string) __('admin.attributes.types.'.$this->type);
    }

    public function activityLabel(): string
    {
        return $this->tr('name');
    }
}
