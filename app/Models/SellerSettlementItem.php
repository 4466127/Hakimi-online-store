<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * کدام ردیف دفتر فروشنده با کدام تسویه و چقدر پرداخت شد (تسویه‌ی جزئی).
 * یک ردیف دفتر هیچ‌وقت بیشتر از مبلغ خودش تسویه نمی‌شود. ویرایش و حذف ندارد.
 *
 * @property int $settlement_id
 * @property int $seller_transaction_id
 * @property string $amount
 */
class SellerSettlementItem extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Settlement items are immutable.'));
        static::deleting(fn () => throw new LogicException('Settlement items are immutable.'));
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class, 'settlement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SellerTransaction::class, 'seller_transaction_id');
    }
}
