<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\SellerStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\Seller;
use App\Services\Delivery\DeliveryCalculator;
use App\Services\Orders\CheckoutData;
use App\Services\Orders\CheckoutService;
use App\Services\Settings;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Support\Str;

/**
 * کمک‌های تست سفارش: داده‌ی پایه (ولایت‌ها، منطقه‌ی کابل، روش‌های پرداخت)، فروشنده، جنس، سبد و ثبت سفارش.
 */
trait BuildsOrders
{
    protected function seedOrderBasics(): void
    {
        $this->seed([ProvinceSeeder::class, DeliveryZoneSeeder::class, PaymentMethodSeeder::class]);
        // سقف سفارش روزانه در تست‌ها خاموش است (تست‌هایی که چند سفارش می‌سازند)؛
        // خود سقف در CheckoutPageTest و CheckoutServiceTest با مقدار صریح آزمایش می‌شود.
        app(Settings::class)->set('orders.max_per_day', 0);
    }

    protected function approvedSeller(): Seller
    {
        return Seller::factory()->status(SellerStatus::Approved)->create();
    }

    protected function categoryWithRate(string|int|null $rate): Category
    {
        return Category::factory()->create(['commission_rate' => $rate]);
    }

    /**
     * یک نوع آماده‌ی فروش. $seller خالی = جنس خود بازارک.
     *
     * @param  array<string, mixed>  $variant
     */
    protected function sellableVariant(?Seller $seller = null, ?Category $category = null, array $variant = []): ProductVariant
    {
        $factory = Product::factory()->active()->withVariant($variant);
        $attributes = [];
        if ($seller !== null) {
            $attributes['seller_id'] = $seller->id;
        }
        if ($category !== null) {
            $attributes['category_id'] = $category->id;
        }

        return $factory->create($attributes)->variants()->firstOrFail();
    }

    /** @param  array<int, array{0: ProductVariant, 1: int}>  $lines */
    protected function cartWith(array $lines): Cart
    {
        $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'customer_id' => null, 'expires_at' => now()->addDay()]);
        foreach ($lines as [$variant, $qty]) {
            $cart->items()->create(['variant_id' => $variant->id, 'qty' => $qty]);
        }

        return $cart;
    }

    /** اولین روز تحویل ممکن در کابل. */
    protected function firstDeliveryDate(): string
    {
        $calculator = app(DeliveryCalculator::class);
        $zone = $calculator->zoneFor(Province::query()->where('code', 'KAB')->firstOrFail());

        return $calculator->options($zone, 0)[0]->dateString();
    }

    /** اولین روزی که می‌شود جنس را حضوری از نمایندگی گرفت (بی کرایه). */
    protected function firstPickupDate(): string
    {
        return app(DeliveryCalculator::class)->pickupOptions()[0]->dateString();
    }

    /** @param  array<string, mixed>  $overrides */
    protected function checkoutData(array $overrides = []): CheckoutData
    {
        $delivery = ($overrides['deliveryMethod'] ?? 'delivery') === 'delivery';
        $defaults = [
            'purchaserName' => 'احمد',
            'purchaserPhone' => '0700111222',
            'recipientName' => 'احمد',
            'recipientPhone' => '0700111222',
            'deliveryMethod' => 'delivery',
            'paymentMethodCode' => $delivery ? 'cod' : 'cash_point',
            'provinceId' => $delivery ? Province::query()->where('code', 'KAB')->value('id') : null,
            'city' => $delivery ? 'کابل' : null,
            'addressLine' => $delivery ? 'کارته‌ی چهار، سرک اول' : null,
            // هر دو روش تحویل روز می‌خواهند؛ حضوری بی کرایه
            'deliveryDate' => $delivery ? $this->firstDeliveryDate() : $this->firstPickupDate(),
        ];

        return new CheckoutData(...($overrides + $defaults));
    }

    /**
     * @param  array<int, array{0: ProductVariant, 1: int}>  $lines
     * @param  array<string, mixed>  $overrides
     */
    protected function placeOrder(array $lines, array $overrides = []): Order
    {
        return app(CheckoutService::class)->place($this->cartWith($lines), $this->checkoutData($overrides));
    }
}
