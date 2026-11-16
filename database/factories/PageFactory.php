<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => 'page-'.fake()->unique()->numerify('#####'),
            'title' => ['fa' => 'صفحه‌ی آزمایشی'],
            'body' => ['fa' => '<p>متن آزمایشی صفحه.</p>'],
            'is_active' => true,
            'show_in_footer' => true,
            'position' => 0,
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (): array => ['body' => ['fa' => '']]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
