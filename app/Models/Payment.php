<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * یک پرداخت (هر تلاش یا دریافت پول). وضعیت و مبلغ‌های دریافتی را فقط سرویس‌های پرداخت (forceFill) عوض می‌کنند.
 *
 * status: awaiting_collection (نقد، هنوز نگرفته‌ایم) | pending (آنلاین، منتظر نتیجه) | paid | failed | refunded | partially_refunded
 * هرگز شماره‌ی کارت یا CVV اینجا نیست.
 *
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property int|null $seller_order_id
 * @property string $method_code
 * @property string $amount
 * @property string $status
 * @property string $idempotency_key
 */
class Payment extends Model
{
    public const AWAITING_COLLECTION = 'awaiting_collection';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $guarded = ['id', 'uuid', 'status', 'amount', 'amount_received', 'paid_at', 'idempotency_key', 'receipt_number'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'collected_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'attempt_no' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
