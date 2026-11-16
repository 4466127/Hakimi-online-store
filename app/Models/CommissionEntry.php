<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * سند مالی یک فروش تحویل‌شده (sale) یا برگشت آن (reversal)، با نرخ و مبلغ کمیسیونِ همان لحظه.
 * تغییر نرخ در آینده روی این سند اثر ندارد. سند هیچ‌وقت ویرایش یا حذف نمی‌شود؛ اصلاح = سند برگشت.
 * seller_id خالی = فروش خود بازارک (جدا از فروش فروشندگان).
 *
 * @property int|null $seller_id
 * @property string $kind
 * @property string $gross_amount
 * @property string $commission_rate
 * @property string $commission_amount
 * @property string $seller_amount
 */
class CommissionEntry extends Model
{
    public const UPDATED_AT = null;

    public const KIND_SALE = 'sale';

    public const KIND_REVERSAL = 'reversal';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommissionEntry $entry): void {
            $entry->uuid ??= (string) Str::uuid();
        });
        static::updating(fn () => throw new LogicException('Commission entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Commission entries are immutable.'));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_id');
    }

    /** فروش فروشندگان (نه فروش خود بازارک). */
    public function scopeMarketplace(Builder $query): Builder
    {
        return $query->whereNotNull('seller_id');
    }

    /** فروش خود بازارک. */
    public function scopeOwnStore(Builder $query): Builder
    {
        return $query->whereNull('seller_id');
    }

    public function isOwnStore(): bool
    {
        return $this->seller_id === null;
    }

    public function rateSourceLabel(): string
    {
        return (string) __('commission.rate_sources.'.$this->rate_source);
    }
}
