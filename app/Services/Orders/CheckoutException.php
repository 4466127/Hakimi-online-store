<?php

declare(strict_types=1);

namespace App\Services\Orders;

use RuntimeException;

/**
 * خطای ثبت سفارش که مشتری باید ببیند. $reason کلید پیام در lang/fa/checkout.php (errors.*) است.
 * کد پایدار برای تست و لاگ: `checkout.<reason>`.
 */
class CheckoutException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('checkout.'.$reason);
    }

    public function userMessage(): string
    {
        return (string) __('checkout.errors.'.$this->reason);
    }
}
