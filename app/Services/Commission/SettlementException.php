<?php

declare(strict_types=1);

namespace App\Services\Commission;

use RuntimeException;

/**
 * خطای تسویه یا اصلاح حساب فروشنده که مدیر باید ببیند.
 * $reason کلید پیام در `lang/fa/commission.php` (errors.*) است؛ کد پایدار: `settlement.<reason>`.
 */
class SettlementException extends RuntimeException
{
    /** @param  array<string, string>  $params  عددها/تاریخ‌های داخل پیام */
    public function __construct(public readonly string $reason, public readonly array $params = [])
    {
        parent::__construct('settlement.'.$reason);
    }

    public function userMessage(): string
    {
        return (string) __('commission.errors.'.$this->reason, $this->params);
    }
}
