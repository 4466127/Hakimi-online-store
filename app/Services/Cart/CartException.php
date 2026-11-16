<?php

declare(strict_types=1);

namespace App\Services\Cart;

use RuntimeException;

/**
 * خطای سبد که مشتری باید ببیند. $reason کلید پیام در lang/fa/cart.php (errors.*) است:
 * unavailable (فروخته نمی‌شود) | out_of_stock (موجودی ندارد) | not_found (خط مال این سبد نیست)
 */
class CartException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function userMessage(): string
    {
        return (string) __('cart.errors.'.$this->reason);
    }
}
