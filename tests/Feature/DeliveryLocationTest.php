<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Province;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderWorkflow;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * موقعیت تحویل روی نقشه: انتخاب در تسویه، ذخیره روی سفارش، دیدن و «مسیریابی» برای پیکِ همان سفارش.
 */
class DeliveryLocationTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل',
            'address_line' => 'کارته‌ی چهار، سرک اول',
            'delivery_date' => $this->firstDeliveryDate(),
            'payment_method_code' => 'cod',
        ], $overrides);
    }

    private function guestCart(): void
    {
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 1]]);
        $this->withCookie(CartService::COOKIE, $cart->token);
    }

    public function test_checkout_shows_the_map_buttons_and_manual_address_stays(): void
    {
        $this->guestCart();

        $this->get('/checkout')->assertOk()
            ->assertSee(__('checkout.location.choose'))
            ->assertSee(__('checkout.location.use_gps'))
            ->assertSee('name="delivery_lat"', false)
            ->assertSee('tile.openstreetmap.org', false)
            ->assertSee('name="address_line"', false);
    }

    public function test_map_can_be_switched_off(): void
    {
        config(['store.maps.enabled' => false]);
        $this->guestCart();

        $this->get('/checkout')->assertOk()
            ->assertDontSee(__('checkout.location.choose'))
            ->assertSee('name="address_line"', false);
    }

    public function test_guest_order_keeps_the_chosen_location_with_the_address(): void
    {
        $this->guestCart();

        $this->post('/checkout', $this->form([
            'delivery_lat' => '34.5553219876',
            'delivery_lng' => '69.2075123',
            'delivery_location_source' => 'gps',
        ]))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->customer_id);
        $this->assertSame('34.5553220', $order->delivery_lat);
        $this->assertSame('69.2075120', $order->delivery_lng);
        $this->assertSame('gps', $order->delivery_location_source);
        $this->assertSame('کارته‌ی چهار، سرک اول', $order->address_line);
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=34.555322,69.207512', $order->directionsUrl());

        // صفحه‌ی سفارش مشتری: فقط «ثبت شده»، بی خود عددها
        $this->get(route('order.show', ['uuid' => $order->uuid]))->assertOk()
            ->assertSee(__('checkout.location.saved_on_order'))
            ->assertDontSee('34.555322');
    }

    public function test_order_without_location_still_works(): void
    {
        $this->guestCart();

        $this->post('/checkout', $this->form())->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->delivery_lat);
        $this->assertFalse($order->hasLocation());
        $this->assertNull($order->directionsUrl());
    }

    public function test_location_outside_afghanistan_or_half_a_point_is_refused(): void
    {
        $this->guestCart();

        $this->post('/checkout', $this->form(['delivery_lat' => '51.5', 'delivery_lng' => '-0.12']))
            ->assertSessionHasErrors(['delivery_lat' => __('checkout.location.invalid')]);
        $this->post('/checkout', $this->form(['delivery_lat' => '34.55']))
            ->assertSessionHasErrors('delivery_lng');
        $this->post('/checkout', $this->form(['delivery_lat' => 'abc', 'delivery_lng' => '69.2']))
            ->assertSessionHasErrors('delivery_lat');
        $this->post('/checkout', $this->form(['delivery_lat' => '34.55', 'delivery_lng' => '69.2', 'delivery_location_source' => 'evil']))
            ->assertSessionHasErrors('delivery_location_source');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_pickup_order_does_not_keep_a_location(): void
    {
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        $order = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]], [
            'deliveryMethod' => 'pickup', 'deliveryLat' => '34.5', 'deliveryLng' => '69.2',
        ]);

        $this->assertNull($order->delivery_lat);
        $this->assertFalse($order->hasLocation());
    }

    public function test_only_the_assigned_courier_and_order_staff_see_the_location(): void
    {
        $order = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]], [
            'deliveryLat' => '34.531', 'deliveryLng' => '69.165', 'deliveryLocationSource' => 'map',
        ]);
        $courier = User::factory()->create(['role' => UserRole::Courier]);
        $other = User::factory()->create(['role' => UserRole::Courier]);
        app(OrderWorkflow::class)->assignCourier($order, $courier);
        $url = OrderResource::getUrl('view', ['record' => $order]);
        $directions = 'https://www.google.com/maps/dir/?api=1&amp;destination=34.531,69.165';

        $this->actingAs($courier)->get($url)->assertOk()
            ->assertSee(__('admin.orders.directions'))
            ->assertSee($directions, false)
            ->assertSee('34.531,69.165');

        $this->actingAs($other)->get($url)->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get($url)->assertOk()
            ->assertSee(__('admin.orders.directions'));

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))->get($url)->assertForbidden();
    }
}
