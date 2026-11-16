<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numerify('#####');

        return [
            'slug' => 'brand-'.$n,
            'name' => 'Brand '.$n,
            'is_active' => true,
            'position' => 0,
        ];
    }
}
