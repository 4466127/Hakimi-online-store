<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Num;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قاعده‌ی کمیسیون یک جنس مشخص (اولویت بالاتر از دسته و فروشنده؛ فقط «استثنا»ی فروشنده بر آن غلبه می‌کند).
 * درصدی (rate) یا مبلغ ثابت برای هر عدد (fixed_amount)، با تاریخ شروع/پایان اختیاری و روشن/خاموش.
 *
 * @property int $product_id
 * @property string $type
 * @property string $rate
 * @property string|null $fixed_amount
 */
class ProductCommissionRate extends Model
{
    use LogsActivity;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = ['product_id', 'type', 'rate', 'fixed_amount', 'starts_at', 'ends_at', 'is_active', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** قاعده‌هایی که در این لحظه روشن و در بازه‌ی تاریخ‌اند. */
    public function scopeActiveAt(Builder $query, DateTimeInterface $at): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function activityLabel(): string
    {
        return $this->type === self::TYPE_FIXED
            ? Num::format((string) $this->fixed_amount, 0).' AFN'
            : $this->rate.'%';
    }
}
