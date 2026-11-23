<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $amount_received
 * @property string $status
 * @property string $idempotency_key
 * @property string|null $receipt_number
 * @property string|null $collected_at_type
 * @property string|null $courier_cash_status
 * @property bool $delivery_code_verified
 */
class Payment extends Model
{
    public const AWAITING_COLLECTION = 'awaiting_collection';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    /** محل گرفتن پول نقد. */
    public const AT_DOOR = 'door';

    public const AT_POINT = 'point';

    /** پولی که پیک گرفته و هنوز به صندوق نرسیده. */
    public const HELD_BY_COURIER = 'held_by_courier';

    public const HANDED_OVER = 'handed_over';

    /** شماره‌ی رسید: RC-100001، RC-100002، … (گفتنش در تلفن آسان است و با شماره‌ی سفارش قاتی نمی‌شود). */
    public const RECEIPT_PREFIX = 'RC-';

    public const RECEIPT_BASE = 100000;

    protected $guarded = ['id', 'uuid', 'status', 'amount', 'amount_received', 'paid_at', 'idempotency_key', 'receipt_number'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'collected_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'attempt_no' => 'integer',
            'delivery_code_verified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    public static function receiptFor(int $id): string
    {
        return self::RECEIPT_PREFIX.(self::RECEIPT_BASE + $id);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_to');
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(CollectionPoint::class);
    }

    /** پول گرفته‌شده‌ای که هنوز دست پیک است (گزارش مدیر و تسویه‌ی پیک). */
    public function scopeHeldByCourier(Builder $query): Builder
    {
        return $query->where('status', self::PAID)->where('courier_cash_status', self::HELD_BY_COURIER);
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** هنوز منتظر گرفتن پول نقد است. */
    public function isAwaitingCash(): bool
    {
        return $this->status === self::AWAITING_COLLECTION;
    }
}
