<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک خط سفارش. نام، قیمت و **قاعده‌ی کمیسیون** هنگام ثبت سفارش کاپی می‌شود و بعد عوض نمی‌شود
 * (تغییر بعدی نرخ یا قیمت روی سفارش قبلی اثر نمی‌گذارد).
 *
 * @property int $id
 * @property int $order_id
 * @property int $seller_order_id
 * @property int|null $variant_id
 * @property int|null $product_id
 * @property int|null $seller_id
 * @property string $product_name
 * @property string|null $variant_label
 * @property string $unit_price
 * @property int $qty
 * @property string $line_total
 * @property string $commission_rate
 * @property string $rate_source
 * @property string $commission_amount
 * @property string $seller_amount
 */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'qty_cancelled' => 'integer',
            'qty_returned' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** تعداد فعال (نه لغو و نه برگشت‌شده). */
    public function activeQty(): int
    {
        return max(0, $this->qty - $this->qty_cancelled - $this->qty_returned);
    }
}
