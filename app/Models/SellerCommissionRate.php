<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نرخ کمیسیون ویژه‌ی یک فروشنده: برای همه‌ی دسته‌ها (category_id خالی) یا یک دسته و زیردسته‌هایش،
 * با تاریخ شروع و ختم اختیاری. مثال: «۳ ماه کمیسیون ۰٪ برای فروشنده‌ی اینستاگرامی».
 *
 * @property int $seller_id
 * @property int|null $category_id
 * @property string $rate
 */
class SellerCommissionRate extends Model
{
    use LogsActivity;

    protected $fillable = ['seller_id', 'category_id', 'rate', 'starts_at', 'ends_at', 'reason', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'category_id' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /** نرخ‌هایی که در این لحظه فعال است. */
    public function scopeActiveAt(Builder $query, DateTimeInterface $at): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function reasonLabel(): string
    {
        $labels = (array) __('commission.reasons');

        return (string) ($labels[$this->reason ?? 'manual'] ?? $this->reason);
    }

    public function activityLabel(): string
    {
        return $this->rate.'%';
    }
}
