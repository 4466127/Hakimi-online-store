<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        static::deleting(fn () => throw new LogicException('Settlements cannot be deleted.'));
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
