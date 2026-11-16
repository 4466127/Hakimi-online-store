<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * یک ردیف دفتر حساب فروشنده: طلب از فروش (earning)، برگشت (reversal)، پرداخت/تسویه (payout)،
 * اصلاح دستی (adjustment). مبلغ مثبت = طلب فروشنده زیاد شد. ویرایش و حذف ندارد.
 *
 * @property string $type
 * @property string $amount
 * @property string $balance_after
 */
class SellerTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['earning', 'reversal', 'payout', 'adjustment'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Seller transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Seller transactions are immutable.'));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function commissionEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return (string) __('commission.transaction_types.'.$this->type);
    }
}
