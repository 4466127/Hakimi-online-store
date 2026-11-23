<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * تسویه: پولی که به فروشنده داده شد (نقد، حساب‌پی، انتقال بانکی). با ثبت آن، مانده‌ی فروشنده کم می‌شود.
 * اشتباه با «اصلاح حساب» درست می‌شود، نه با حذف.
 *
 * @property int $seller_id
 * @property string $amount
 * @property string $method
 */
class SellerSettlement extends Model
{
    use LogsActivity;

    public const METHODS = ['cash', 'hesabpay', 'bank_transfer'];

    public const NUMBER_PREFIX = 'SP-';

    public const NUMBER_BASE = 100000;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'period_from' => 'date',
            'period_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerSettlement $settlement): void {
            $settlement->uuid ??= (string) Str::uuid();
        });
        static::updating(function (SellerSettlement $settlement): void {
            // مبلغ، فروشنده و تاریخ پرداخت سند مالی‌اند؛ فقط شماره (یک بار، بعد از ساختن) گذاشته می‌شود
            if (array_diff(array_keys($settlement->getDirty()), ['number']) !== [] || $settlement->getOriginal('number') !== null) {
                throw new LogicException('Settlements are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Settlements cannot be deleted.'));
    }

    public static function numberFor(int $id): string
    {
        return self::NUMBER_PREFIX.(self::NUMBER_BASE + $id);
    }

    /** ردیف‌های دفتر که با همین تسویه پرداخت شد. */
    public function items(): HasMany
    {
        return $this->hasMany(SellerSettlementItem::class, 'settlement_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        return (string) __('commission.methods.'.$this->method);
    }

    public function activityLabel(): string
    {
        return $this->amount.' AFN';
    }
}
