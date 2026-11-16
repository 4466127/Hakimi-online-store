<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک خط سبد: نوع (variant) و تعداد (عدد صحیح).
 *
 * @property int $cart_id
 * @property int $variant_id
 * @property int $qty
 */
class CartItem extends Model
{
    protected $fillable = ['cart_id', 'variant_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
