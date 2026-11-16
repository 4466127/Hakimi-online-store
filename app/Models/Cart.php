<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سبد خرید. مهمان با توکن (کوکی)، عضو با customer_id. قیمت ذخیره نمی‌کند (CartService زنده می‌خواند).
 *
 * @property int $id
 * @property string $token
 * @property int|null $customer_id
 */
class Cart extends Model
{
    protected $fillable = ['token', 'customer_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
