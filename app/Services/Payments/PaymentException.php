<?php

declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

/**
 * خطای کار با پول که کاربر (مدیر، کارمند یا پیک) باید ببیند.
 * $reason کلید پیام در `lang/fa/payments.php` (errors.*) است؛ کد پایدار برای تست و لاگ: `payment.<reason>`.
 */
class PaymentException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('payment.'.$reason);
    }

    public function userMessage(): string
    {
        return (string) __('payments.errors.'.$this->reason);
    }
}
