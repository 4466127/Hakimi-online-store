<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * یک بار استفاده از کد تخفیف (هر سفارش حداکثر یکی). حذف نمی‌شود؛ لغو کامل سفارش ← `released`.
 * فقط DiscountCodeService می‌سازد و عوض می‌کند.
 *
 * @property int $discount_code_id
 * @property int $order_id
 * @property string $phone
 * @property string $amount
 * @property string $returned_amount
 * @property string $status
 */
class DiscountCodeRedemption extends Model
{
    public const ACTIVE = 'active';

    public const RELEASED = 'released';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Discount code redemptions are history and cannot be deleted.'));
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
