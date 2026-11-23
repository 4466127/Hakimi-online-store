<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * سفارشِ یک فروشنده داخل یک سفارش والد: وضعیت و صورت مالی خودش را دارد.
 * seller_id خالی = اجناس خود بازارک (کمیسیون ندارد).
 *
 * صورت مالی (docs/COMMISSION_AND_SELLER_SETTLEMENT.md بخش ۳):
 *   customer_paid_products = gross_subtotal − seller_discount_total − platform_discount_total
 *   commissionable_amount  = gross_subtotal − seller_discount_total
 *   seller_payable         = commissionable_amount − commission_total − gateway_fee_charged_to_seller
 *                            − other_deductions − refund_adjustments
 *
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $number
 * @property int|null $seller_id
 * @property string $status
 * @property string $payment_status
 * @property string $gross_subtotal
 * @property string $commission_total
 * @property string $seller_payable
 */
class SellerOrder extends Model
{
    /** وضعیت و پرداخت و مبلغ‌ها را فقط سرویس‌ها (forceFill) می‌گذارند. */
    protected $guarded = ['id', 'uuid', 'number', 'status', 'payment_status', 'gross_subtotal', 'seller_discount_total',
        'platform_discount_total', 'customer_paid_products', 'commissionable_amount', 'commission_total', 'delivery_fee',
        'delivery_fee_payee', 'gateway_fee_allocated', 'gateway_fee_charged_to_seller', 'refund_adjustments',
        'other_deductions', 'seller_payable'];

    protected function casts(): array
    {
        return [
            'handover_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** برای فروشنده‌ی «بازارک» (بی فروشنده)؟ */
    public function isPlatform(): bool
    {
        return $this->seller_id === null;
    }
}
