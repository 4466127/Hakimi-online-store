<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /** رمز همه‌ی مشتریان آزمایشی */
    public const PASSWORD = 'password1';

    public function definition(): array
    {
        return [
            'name' => 'مشتری آزمایشی',
            'phone' => '07'.fake()->unique()->numerify('########'),
            'password' => self::PASSWORD,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
