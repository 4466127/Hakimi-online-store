<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Support\Money;

/**
 * قاعده‌ی کمیسیونی که برای یک فروش به کار می‌رود و اینکه از کجا آمد.
 *
 * source (به ترتیب اولویت):
 *   exception (نرخ «استثنا»ی فروشنده) → product (قاعده‌ی جنس) → category (دسته یا بالادست)
 *   → seller (نرخ عادی فروشنده) → default (پیش‌فرض تنظیمات) ؛ platform = جنس خود بازارک (۰).
 * type: percent (درصد از مبلغ مشمول) | fixed (مبلغ ثابت برای هر عدد، هیچ‌وقت بیشتر از مبلغ خط).
 * ruleId: شناسه‌ی ردیف قاعده (seller_commission_rates برای exception/seller، product_commission_rates برای product).
 */
final readonly class AppliedRate
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    public function __construct(
        public string $rate,
        public string $source,
        public ?int $categoryId = null,
        public ?int $overrideId = null,
        public ?\DateTimeInterface $endsAt = null,
        public string $type = self::PERCENT,
        public ?string $fixedAmount = null,
    ) {}

    public function isFixed(): bool
    {
        return $this->type === self::FIXED;
    }

    /**
     * کمیسیون یک خط: درصدی ← درصد از مبلغ؛ ثابت ← مبلغ × تعداد، ولی نه بیشتر از خود مبلغ
     * (سهم فروشنده هیچ‌وقت منفی نمی‌شود). همه با bcmath.
     */
    public function commissionFor(string $amount, int $qty): string
    {
        if (Money::compare($amount, 0) <= 0) {
            return '0.00';
        }
        if ($this->isFixed()) {
            $fixed = Money::multiply((string) $this->fixedAmount, $qty);

            return Money::compare($fixed, $amount) > 0 ? Money::of($amount) : $fixed;
        }

        return Money::percentOf($amount, $this->rate);
    }
}
