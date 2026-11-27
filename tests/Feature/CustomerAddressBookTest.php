<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Province;
use App\Services\Cart\CartService;
use App\Services\Customers\CustomerAddressBook;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * دفترچه‌ی آدرس مشتری (۶ قوس ۱۴۰۵): آدرس سفارش نگه داشته می‌شود، بار دوم خودکار پر می‌شود،
 * تکراری ساخته نمی‌شود، سقف دارد و مشتری فقط آدرس خودش را می‌بیند و پاک می‌کند.
 */
class CustomerAddressBookTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    private function kabul(): int
    {
        return (int) Province::query()->where('code', 'KAB')->value('id');
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => $this->kabul(),
            'city' => 'کابل',
            'district' => 'کارته‌ی چهار',
            'address_line' => 'سرک اول، خانه‌ی سبز',
            'delivery_instructions' => 'زنگ دوم',
            'delivery_date' => $this->firstDeliveryDate(),
            'payment_method_code' => 'cod',
        ], $overrides);
    }

    /** مشتری عضو با یک جنس در سبدش، سفارش می‌دهد. */
    private function memberOrders(Customer $customer, array $form = []): void
    {
        // هر مشتری یک سبد دارد (ستون یکتا): سبد قبلی جایش را به سبد تازه می‌دهد
        Cart::query()->where('customer_id', $customer->id)->delete();
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]), 1]]);
        $cart->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')->post('/checkout', $this->form($form))->assertRedirect();
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['phone' => '0700111222', 'name' => 'احمد حکیمی']);
    }

    public function test_member_order_saves_the_address_and_the_same_address_is_not_saved_twice(): void
    {
        $customer = $this->customer();

        $this->memberOrders($customer, ['delivery_lat' => '34.5', 'delivery_lng' => '69.1', 'delivery_location_source' => 'map']);
        $address = CustomerAddress::query()->sole();
        $this->assertSame($customer->id, $address->customer_id);
        $this->assertSame('سرک اول، خانه‌ی سبز', $address->address_line);
        $this->assertSame('کارته‌ی چهار', $address->district);
        $this->assertSame('34.5000000', (string) $address->delivery_lat);

        // همان آدرس با فاصله‌ی اضافه و بی نقشه: ردیف تازه ساخته نمی‌شود و موقعیت قبلی می‌ماند
        $this->travel(1)->hours();
        $this->memberOrders($customer, ['address_line' => '  سرک اول،   خانه‌ی سبز ']);
        $this->assertSame(1, CustomerAddress::query()->count());
        $fresh = $address->fresh();
        $this->assertTrue($fresh->last_used_at->gt($address->last_used_at));
        $this->assertSame('34.5000000', (string) $fresh->delivery_lat);

        // آدرس دیگر ← ردیف دوم
        $this->memberOrders($customer, ['address_line' => 'خیرخانه، سرک سوم']);
        $this->assertSame(2, CustomerAddress::query()->count());
    }

    public function test_nothing_is_saved_when_the_customer_unticks_it_for_guests_or_for_pickup(): void
    {
        // مهمان (پیش از هر ورود)
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]), 1]]);
        $this->withCookie(CartService::COOKIE, $cart->token)->post('/checkout', $this->form())->assertRedirect();
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, CustomerAddress::query()->count());

        // عضو که تیک «نگه دار» را برداشت
        $customer = $this->customer();
        $this->memberOrders($customer, ['save_address' => '0']);
        $this->assertSame(0, CustomerAddress::query()->count());

        // تحویل حضوری آدرس ندارد
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        $this->memberOrders($customer, [
            'delivery_method' => 'pickup',
            'delivery_date' => null,
            'pickup_date' => $this->firstPickupDate(),
            'payment_method_code' => 'cash_point',
        ]);
        $this->assertSame(0, CustomerAddress::query()->count());
    }

    public function test_checkout_page_lists_saved_addresses_and_fills_the_last_used_one(): void
    {
        $customer = $this->customer();
        $this->memberOrders($customer);
        $this->memberOrders($customer, ['address_line' => 'خیرخانه، سرک سوم', 'district' => 'خیرخانه']);

        Cart::query()->where('customer_id', $customer->id)->delete();
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]), 1]]);
        $cart->update(['customer_id' => $customer->id]);

        $html = $this->actingAs($customer, 'customer')->get('/checkout')
            ->assertOk()
            ->assertSee(__('checkout.saved.help'))
            ->assertSee(__('checkout.saved.new'))
            ->assertSee('سرک اول، خانه‌ی سبز')
            ->assertSee(__('checkout.saved.remember'))
            ->getContent();

        // آخرین آدرس (خیرخانه) در خانه‌ی آدرس نشسته است
        $this->assertMatchesRegularExpression('/id="f-address_line"[^>]*>خیرخانه، سرک سوم<\/textarea>/u', $html);
        $this->assertStringContainsString('id="f-district" type="text" name="district" value="خیرخانه"', $html);
    }

    public function test_guest_checkout_has_no_saved_addresses_or_remember_box(): void
    {
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]), 1]]);

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/checkout')
            ->assertOk()
            ->assertDontSee(__('checkout.saved.new'))
            ->assertDontSee(__('checkout.saved.remember'));
    }

    public function test_address_book_keeps_at_most_ten_and_drops_the_least_recently_used(): void
    {
        $customer = $this->customer();
        for ($i = 1; $i <= CustomerAddressBook::MAX; $i++) {
            $address = new CustomerAddress([
                'recipient_name' => 'احمد', 'recipient_phone' => '0700111222', 'province_id' => $this->kabul(),
                'address_line' => 'آدرس '.$i, 'last_used_at' => now()->subDays(20 - $i),
            ]);
            $address->customer_id = $customer->id;
            $address->save();
        }

        $this->memberOrders($customer, ['address_line' => 'آدرس تازه']);

        $lines = CustomerAddress::query()->where('customer_id', $customer->id)->pluck('address_line');
        $this->assertCount(CustomerAddressBook::MAX, $lines);
        $this->assertContains('آدرس تازه', $lines);
        $this->assertNotContains('آدرس 1', $lines);            // دیرتر از همه استفاده شده بود
        $this->assertContains('آدرس 2', $lines);
    }

    public function test_customer_sees_and_deletes_only_own_addresses(): void
    {
        $customer = $this->customer();
        $this->memberOrders($customer);
        $mine = CustomerAddress::query()->sole();

        $other = Customer::factory()->create();
        $theirs = new CustomerAddress([
            'recipient_name' => 'کریم', 'recipient_phone' => '0788999888', 'province_id' => $this->kabul(),
            'address_line' => 'آدرس شخص دیگر', 'last_used_at' => now(),
        ]);
        $theirs->customer_id = $other->id;
        $theirs->save();

        $this->actingAs($customer, 'customer')->get(route('account.addresses'))
            ->assertOk()
            ->assertSee('سرک اول، خانه‌ی سبز')
            ->assertDontSee('آدرس شخص دیگر');

        // آدرس دیگری را نمی‌تواند پاک کند
        $this->actingAs($customer, 'customer')
            ->delete(route('account.addresses.destroy', ['address' => $theirs->id]))
            ->assertNotFound();
        $this->assertNotNull($theirs->fresh());

        $this->actingAs($customer, 'customer')
            ->delete(route('account.addresses.destroy', ['address' => $mine->id]))
            ->assertRedirect(route('account.addresses'));
        $this->assertNull($mine->fresh());

        $this->actingAs($customer, 'customer')->get(route('account'))
            ->assertOk()
            ->assertSee(route('account.addresses'));
    }

    public function test_addresses_page_needs_a_login(): void
    {
        $this->get(route('account.addresses'))->assertRedirect();
    }
}
