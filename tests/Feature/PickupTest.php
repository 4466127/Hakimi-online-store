<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageStoreSettings;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Delivery\DeliveryCalculator;
use App\Services\Settings;
use App\Services\StoreInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تحویل حضوری از دفتر مرکزی، بی کرایه (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵).
 */
class PickupTest extends TestCase
{
    use RefreshDatabase;

    private function office(?string $address = 'کابل، دشت برچی، سرک بیست متره'): void
    {
        $settings = app(Settings::class);
        $settings->set('store.address', $address);
    }

    public function test_pickup_needs_the_toggle_and_an_office_address(): void
    {
        $this->assertFalse(app(StoreInfo::class)->pickupEnabled(), 'بی آدرس دفتر حضوری نداریم');
        $this->assertNull(app(StoreInfo::class)->pickupAddress());

        $this->office();
        $this->assertTrue(app(StoreInfo::class)->pickupEnabled(), 'پیش‌فرض روشن است');
        $this->assertSame('کابل، دشت برچی، سرک بیست متره', app(StoreInfo::class)->pickupAddress());

        app(Settings::class)->set('delivery.pickup_enabled', false);
        $this->assertFalse(app(StoreInfo::class)->pickupEnabled());
        $this->assertNull(app(StoreInfo::class)->pickupNote());

        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('delivery.pickup_note', '  هر روز ۹ تا ۵  ');
        $this->assertSame('هر روز ۹ تا ۵', app(StoreInfo::class)->pickupNote());
    }

    public function test_pickup_has_no_fee(): void
    {
        $this->assertSame('0.00', app(DeliveryCalculator::class)->pickupFee());
        $this->assertSame('delivery', DeliveryCalculator::METHOD_DELIVERY);
        $this->assertSame('pickup', DeliveryCalculator::METHOD_PICKUP);
    }

    public function test_cart_and_product_pages_offer_free_pickup_with_the_office_address(): void
    {
        $this->office();
        app(Settings::class)->set('delivery.pickup_note', 'پیش از آمدن زنگ بزنید');
        $product = Product::factory()->active()->withVariant(['stock_qty' => 5])->create();
        $variant = $product->variants()->firstOrFail();

        $this->get($product->url())->assertOk()
            ->assertSee(__('store.product.pickup'))
            ->assertSee('کابل، دشت برچی، سرک بیست متره')
            ->assertSee('پیش از آمدن زنگ بزنید');

        $token = $this->post('/cart/add', ['variant_id' => $variant->id])->getCookie(CartService::COOKIE)->getValue();
        $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk()
            ->assertSee(__('cart.pickup_free'))
            ->assertSee('کابل، دشت برچی، سرک بیست متره');
    }

    public function test_pickup_is_hidden_when_turned_off_or_without_an_address(): void
    {
        $product = Product::factory()->active()->withVariant(['stock_qty' => 5])->create();

        $this->get($product->url())->assertOk()->assertDontSee(__('store.product.pickup'));   // بی آدرس

        $this->office();
        app(Settings::class)->set('delivery.pickup_enabled', false);
        $this->get($product->url())->assertOk()->assertDontSee(__('store.product.pickup'));
    }

    public function test_owner_can_change_pickup_from_the_settings_page(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm([
                'name' => 'بازارک',
                'address' => 'کابل، دفتر مرکزی',
                'pickup_enabled' => true,
                'pickup_note' => '  هر روز ۹ تا ۵، جمعه بسته  ',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(Settings::class);
        $this->assertTrue($settings->get('delivery.pickup_enabled'));
        $this->assertSame('هر روز ۹ تا ۵، جمعه بسته', $settings->get('delivery.pickup_note'));
        $this->assertTrue(app(StoreInfo::class)->pickupEnabled());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm(['name' => 'بازارک', 'address' => 'کابل، دفتر مرکزی', 'pickup_enabled' => false, 'pickup_note' => ''])
            ->call('save');

        $this->assertFalse(app(Settings::class)->get('delivery.pickup_enabled'));
        $this->assertNull(app(Settings::class)->get('delivery.pickup_note'));
    }
}
