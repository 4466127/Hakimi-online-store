<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'attr_'.fake()->unique()->numerify('#####'),
            'name' => ['fa' => 'مشخصه‌ی آزمایشی'],
            'type' => 'select',
            'is_variant' => false,
            'is_filterable' => true,
            'show_in_specs' => true,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
