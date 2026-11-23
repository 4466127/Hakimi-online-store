<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Services\Orders\CheckoutException;

/**
 * کد تخفیف پذیرفته نشد. پیام دری در lang/fa/checkout.php (discount.errors.*).
 * از CheckoutException است تا ثبت سفارش با همان راه خطا برگردد.
 */
class DiscountCodeException extends CheckoutException
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(public readonly string $discountReason, public readonly array $params = [])
    {
        parent::__construct('discount_'.$discountReason);
    }

    public function userMessage(): string
    {
        return (string) __('checkout.discount.errors.'.$this->discountReason, $this->params);
    }
}
