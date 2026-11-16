<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * جنس آزمایشی (پیش‌نویس، بی نوع). با ->withVariant() یک نوع ساده می‌گیرد و cache ساخته می‌شود.
 * برای ساختن کامل از راه فورم، از ProductService::save استفاده کنید.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numerify('#####');

        return [
            'category_id' => Category::factory(),
            'slug' => 'product-'.$n,
            'name' => ['fa' => 'جنس آزمایشی '.$n],
            'status' => 'draft',
            'search_text' => '',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active', 'published_at' => now()->subMinute()]);
    }

    public function ownedBy(int $sellerId): static
    {
        return $this->state(fn (): array => ['seller_id' => $sellerId]);
    }

    /** @param  array<string, mixed>  $variant */
    public function withVariant(array $variant = []): static
    {
        return $this->afterCreating(function (Product $product) use ($variant): void {
            ProductVariant::query()->create($variant + [
                'product_id' => $product->id,
                'sku' => 'BZ-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT).'-1',
                'price' => '1000.00',
                'stock_qty' => '5',
                'is_default' => true,
                'is_active' => true,
            ]);
            app(ProductService::class)->refresh($product);
        });
    }
}
