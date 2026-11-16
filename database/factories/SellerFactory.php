<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SellerStatus;
use App\Models\Customer;
use App\Models\Province;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * فروشنده با فورم کامل (همه‌ی قدم‌ها پر). حالت پیش‌فرض: منتظر بررسی.
 *
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => 'individual',
            'full_name' => 'فروشنده‌ی آزمایشی',
            'father_name' => 'عبدالله',
            'tazkira_number' => '1400-0101-'.fake()->unique()->numerify('#####'),
            'store_name' => 'فروشگاه '.fake()->unique()->numerify('#####'),
            'category_ids' => [],
            'province_id' => fn () => Province::query()->value('id'),
            'city' => 'کابل',
            'address' => 'کارته‌ی چهار، سرک اول',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Seller $seller): void {
            $seller->status = SellerStatus::Pending;
            $seller->onboarding_step = 4;
            $seller->terms_accepted_at ??= now();
            $seller->submitted_at ??= now();
        });
    }

    public function status(SellerStatus $status): static
    {
        return $this->afterMaking(function (Seller $seller) use ($status): void {
            $seller->status = $status;
        });
    }
}
