<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Services\StoreInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نمایندگی: جایی که مشتری جنس را حضوری می‌گیرد و پول نقد می‌دهد (و پیک پول را تحویل می‌دهد).
 * «دفتر مرکزی» اولین نمایندگی است؛ آدرس خالی = آدرس فروشگاه از تنظیمات.
 *
 * @property int $id
 * @property string $type
 * @property string|null $address
 * @property bool $is_active
 */
class CollectionPoint extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'type', 'province_id', 'city', 'address', 'phone', 'hours', 'instructions', 'user_id', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'hours' => 'array',
            'instructions' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** آدرس نشان‌داده‌شده: آدرس خودش، وگرنه (دفتر مرکزی) آدرس فروشگاه. */
    public function displayAddress(): ?string
    {
        return $this->address !== null && $this->address !== ''
            ? $this->address
            : app(StoreInfo::class)->pickupAddress();
    }
}
