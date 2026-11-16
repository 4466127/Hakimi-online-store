<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * منطقه‌ی تحویل (فعلاً «کابل») با قاعده‌ی کرایه‌ی خودش:
 * روزهای تحویل و کرایه‌ی هر کدام (`slots`)، پله‌های وزنی (`weightTiers`)، ساعت قطع سفارش،
 * روزهای بی تحویل، وزن پیش‌فرض جنس بی وزن، و سقف وزن.
 *
 * @property int $id
 * @property int|null $province_id
 * @property bool $is_active
 * @property int $cutoff_hour
 * @property list<int>|null $days_off
 * @property int $default_item_weight_grams
 * @property int|null $max_weight_grams
 */
class DeliveryZone extends Model
{
    use HasTranslations, LogsActivity;

    protected $fillable = [
        'name', 'province_id', 'is_active', 'position', 'cutoff_hour', 'days_off',
        'default_item_weight_grams', 'max_weight_grams',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'days_off' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
            'cutoff_hour' => 'integer',
            'default_item_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** روزهای تحویل به ترتیب (روز اول زودترین). */
    public function slots(): HasMany
    {
        return $this->hasMany(DeliverySlot::class, 'zone_id')->orderBy('day_rank');
    }

    public function weightTiers(): HasMany
    {
        return $this->hasMany(DeliveryWeightTier::class, 'zone_id')->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    public function activityLabel(): string
    {
        return $this->tr('name');
    }
}
