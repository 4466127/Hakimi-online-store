<?php

declare(strict_types=1);

namespace App\Services\Orders;

use RuntimeException;

/**
 * تغییر وضعیت ممنوع (مثلاً از «تحویل شد» به «تأیید شد»). کد پایدار: `order.transition_denied` / `order.reason_required`.
 */
class OrderTransitionException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?string $from = null, public readonly ?string $to = null)
    {
        parent::__construct('order.'.$reason);
    }

    public function userMessage(): string
    {
        return $this->reason === 'transition_denied'
            ? (string) __('checkout.transition.denied', [
                'from' => (string) __('checkout.status.'.$this->from),
                'to' => (string) __('checkout.status.'.$this->to),
            ])
            : (string) __('checkout.transition.reason_required');
    }
}
