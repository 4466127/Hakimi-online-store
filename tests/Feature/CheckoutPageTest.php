<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Province;
use App\Services\Cart\CartService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۲-۲: صفحه‌ی تسویه، ثبت سفارش از سایت، صفحه‌ی سفارش، پیگیری و «سفارش‌های من».
 * (قاعده‌های مالی و موجودی در CheckoutServiceTest آزمایش می‌شود؛ اینجا راه مشتری.)
 */
class CheckoutPageTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    /** سبد مهمان با کوکی، تا درخواست‌های بعدی همان سبد را ببینند. */
    private function guestCart(array $lines): void
    {
        $cart = $this->cartWith($lines);
        $this->withCookie(CartService::COOKIE, $cart->token);
    }

    /** @return array<string, mixed> */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل',
            'address_line' => 'کارته‌ی چهار، سرک اول، خانه‌ی سبز',
            'delivery_date' => $this->firstDeliveryDate(),
            'payment_method_code' => 'cod',
        ], $overrides);
    }

    public function test_checkout_page_shows_lines_days_and_payment_methods(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '1200.00', 'stock_qty' => 5]);
        $this->guestCart([[$variant, 2]]);

        $response = $this->get('/checkout');

        $response->assertOk()
            ->assertSee($variant->product->tr('name'))
            ->assertSee(__('checkout.delivery.day_heading'))
            ->assertSee(__('checkout.payment.heading'))
            ->assertSee($this->firstDeliveryDate())          // روز تحویل در فورم
            ->assertSee('pickup');                           // تحویل حضوری هم گزینه است
    }

    public function test_empty_cart_goes_back_to_cart_page(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart.show'));
    }

    public function test_guest_places_cash_on_delivery_order_and_sees_it(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '1500.00', 'stock_qty' => 4]);
        $this->guestCart([[$variant, 2]]);

        $response = $this->post('/checkout', $this->formData(['customer_note' => 'رنگ سیاه بهتر است']));

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('order.show', ['uuid' => $order->uuid]));

        $this->assertSame('احمد حکیمی', $order->purchaser_name);
        $this->assertSame('0700111222', $order->purchaser_phone);
        $this->assertSame('احمد حکیمی', $order->recipient_name);     // گیرنده خود خریدار
        $this->assertFalse((bool) $order->is_gift);
        $this->assertSame('delivery', $order->delivery_method);
        $this->assertSame('cod', $order->payment_method_code);
        $this->assertSame('3000.00', $order->subtotal);
        $this->assertSame('رنگ سیاه بهتر است', $order->customer_note);
        $this->assertNull($order->customer_id);                       // مهمان
        $this->assertCount(1, $order->items);
        $this->assertCount(1, $order->sellerOrders);

        // صفحه‌ی سفارش
        $this->followingRedirects()->get(route('order.show', ['uuid' => $order->uuid]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee(__('checkout.order.thanks_heading'));
    }

    public function test_order_placed_empties_the_cart(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 9]);
        $cart = $this->cartWith([[$variant, 1]]);
        $this->withCookie(CartService::COOKIE, $cart->token);

        $this->post('/checkout', $this->formData());

        $this->assertSame(0, $cart->items()->count());
        $this->get('/checkout')->assertRedirect(route('cart.show'));
    }

    public function test_address_is_required_for_home_delivery(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', $this->formData(['address_line' => '']))
            ->assertSessionHasErrors('address_line');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_wrong_phone_is_rejected(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', $this->formData(['purchaser_phone' => '0612345678']))
            ->assertSessionHasErrors('purchaser_phone');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_phone_is_normalized_and_persian_digits_are_accepted(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', $this->formData(['purchaser_phone' => '+93 700 111 222']));

        $this->assertSame('0700111222', Order::query()->value('purchaser_phone'));
    }

    public function test_gift_order_keeps_recipient_separate(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '800.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', $this->formData([
            'is_gift' => '1',
            'recipient_name' => 'زینب حکیمی',
            'recipient_phone' => '۰۷۸۰۱۱۱۲۲۲',
        ]));

        $order = Order::query()->firstOrFail();
        $this->assertTrue((bool) $order->is_gift);
        $this->assertSame('زینب حکیمی', $order->recipient_name);
        $this->assertSame('0780111222', $order->recipient_phone);
        $this->assertSame('احمد حکیمی', $order->purchaser_name);
    }

    public function test_payment_method_that_does_not_fit_delivery_shows_error(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '800.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', $this->formData(['payment_method_code' => 'cash_point']))
            ->assertSessionHas('error', __('checkout.errors.payment_not_for_delivery'));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_online_payment_cannot_place_an_order_yet(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '800.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        // حساب‌پی غیرفعال است (مرحله‌ی ۵): اعتبارسنجی هم آن را رد می‌کند
        $this->post('/checkout', $this->formData(['payment_method_code' => 'hesabpay']))
            ->assertSessionHasErrors('payment_method_code');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_pickup_order_has_no_delivery_fee(): void
    {
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');

        $variant = $this->sellableVariant(null, null, ['price' => '900.00', 'stock_qty' => 9]);
        $this->guestCart([[$variant, 1]]);

        $this->post('/checkout', [
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'pickup',
            'payment_method_code' => 'cash_point',
        ]);

        $order = Order::query()->firstOrFail();
        $this->assertSame('pickup', $order->delivery_method);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('900.00', $order->total);
        $this->assertNotNull($order->collection_point_id);
        $this->assertNull($order->address_line);
    }

    public function test_member_order_is_attached_to_the_customer_and_listed(): void
    {
        $customer = Customer::factory()->create();
        $variant = $this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]);
        $cart = $this->cartWith([[$variant, 1]]);
        $cart->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')->post('/checkout', $this->formData());

        $order = Order::query()->firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);

        $this->actingAs($customer, 'customer')->get(route('account.orders'))
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_order_page_needs_the_right_uuid(): void
    {
        $this->get(route('order.show', ['uuid' => '11111111-1111-1111-1111-111111111111']))->assertNotFound();
    }

    public function test_tracking_with_number_and_phone_opens_the_order(): void
    {
        $order = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '600.00', 'stock_qty' => 9]), 1]]);

        $this->get('/track')->assertOk()->assertSee(__('checkout.track.heading'));

        $this->post('/track', ['number' => 'hs-'.(int) substr((string) $order->number, 3), 'phone' => '۰۷۰۰۱۱۱۲۲۲'])
            ->assertRedirect(route('order.show', ['uuid' => $order->uuid]));
    }

    public function test_tracking_with_wrong_phone_finds_nothing(): void
    {
        $order = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '600.00', 'stock_qty' => 9]), 1]]);

        $this->post('/track', ['number' => $order->number, 'phone' => '0788888888'])
            ->assertSessionHasErrors('number');
    }

    public function test_recipient_phone_can_also_track_the_order(): void
    {
        $order = $this->placeOrder(
            [[$this->sellableVariant(null, null, ['price' => '600.00', 'stock_qty' => 9]), 1]],
            ['isGift' => true, 'recipientName' => 'زینب', 'recipientPhone' => '0780111222'],
        );

        $this->post('/track', ['number' => $order->number, 'phone' => '0780111222'])
            ->assertRedirect(route('order.show', ['uuid' => $order->uuid]));
    }

    public function test_my_orders_needs_login(): void
    {
        $this->get(route('account.orders'))->assertRedirect(route('login'));
    }

    public function test_my_orders_is_empty_for_a_new_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('checkout.mine.empty_heading'));
    }

    public function test_cart_with_a_problem_sends_the_customer_back_to_the_cart(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '600.00', 'stock_qty' => 1]);
        $this->guestCart([[$variant, 1]]);
        $variant->forceFill(['stock_qty' => 0])->save();

        $this->get('/checkout')->assertRedirect(route('cart.show'));
    }
}
