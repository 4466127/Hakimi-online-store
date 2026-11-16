<?php

declare(strict_types=1);

namespace App\Services\Commission;

/**
 * نرخ کمیسیونی که برای یک فروش به کار می‌رود و اینکه از کجا آمد.
 *
 * source: seller (نرخ ویژه‌ی فروشنده) / category (نرخ دسته یا بالادست) / default (پیش‌فرض تنظیمات) / platform (جنس خود بازارک = ۰)
 */
final readonly class AppliedRate
{
    public function __construct(
        public string $rate,
        public string $source,
        public ?int $categoryId = null,
        public ?int $overrideId = null,
        public ?\DateTimeInterface $endsAt = null,
    ) {}
}
