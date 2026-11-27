<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use App\Services\Settings;
use App\Support\Num;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * برگه‌ی بسته‌بندی (۶ قوس ۱۴۰۵): هر فروشنده یک برگه با فرستنده، گیرنده، آدرس، پول و اجناس؛ بی کد تحویل.
 */
class PackingSlipTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    /** سفارش با کد تحویل معلوم (تا نبودنش روی برگه دقیق آزمایش شود). */
    private function order(array $lines, array $overrides = []): Order
    {
        $order = $this->placeOrder($lines, $overrides);
        $order->forceFill(['delivery_code' => '73519'])->save();

        return $order->refresh();
    }

    public function test_slip_shows_sender_recipient_address_cash_and_items_but_not_the_delivery_code(): void
    {
        $seller = $this->approvedSeller();
        $seller->update(['phone' => '0799123456', 'city' => 'کابل', 'address' => 'مندوی، مارکیت نو']);
        $variant = $this->sellableVariant($seller, null, ['price' => '1000.00', 'stock_qty' => 5, 'sku' => 'BOOT-42']);
        $order = $this->order([[$variant, 2]], [
            'recipientName' => 'زهرا حکیمی',
            'recipientPhone' => '0788111222',
            'isGift' => true,
            'deliveryInstructions' => 'دروازه‌ی سبز، زنگ دوم',
        ]);

        $response = $this->actingAs($this->staff())->get(route('admin.packing-slip', ['order' => $order->uuid]));

        $response->assertOk()
            ->assertSee(Num::toFa($order->number))
            ->assertSee(__('admin.packing.sender'))
            ->assertSee($seller->store_name)
            ->assertSee('۰۷۹۹ ۱۲۳ ۴۵۶')
            ->assertSee('مندوی، مارکیت نو')
            ->assertSee('زهرا حکیمی')
            ->assertSee('کارته‌ی چهار، سرک اول')
            ->assertSee('دروازه‌ی سبز، زنگ دوم')
            ->assertSee($variant->product->tr('name'))
            ->assertSee('BOOT-42')
            ->assertSee(__('admin.packing.collect'))
            ->assertSee('۲٬۲۰۰')                       // ۲×۱۰۰۰ + کرایه‌ی ۲۰۰
            ->assertDontSee('73519')
            ->assertDontSee('۷۳۵۱۹');
    }

    public function test_one_slip_per_seller_and_bazarak_is_the_sender_of_its_own_goods(): void
    {
        $seller = $this->approvedSeller();
        $order = $this->order([
            [$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1],
            [$this->sellableVariant($seller, null, ['price' => '800.00', 'stock_qty' => 5]), 1],
        ]);
        $this->assertSame(2, $order->sellerOrders()->count());

        $this->actingAs($this->staff())->get(route('admin.packing-slip', ['order' => $order->uuid]))
            ->assertOk()
            ->assertSee(__('admin.packing.package_of', ['n' => '۱', 'count' => '۲']))
            ->assertSee(__('admin.packing.package_of', ['n' => '۲', 'count' => '۲']))
            ->assertSee($seller->store_name)
            ->assertSee(__('admin.packing.collect_whole'));
    }

    public function test_pickup_slip_shows_the_collection_point_instead_of_an_address(): void
    {
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        $order = $this->order([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]], ['deliveryMethod' => 'pickup']);

        $this->actingAs($this->staff())->get(route('admin.packing-slip', ['order' => $order->uuid]))
            ->assertOk()
            ->assertSee(__('admin.packing.pickup'))
            ->assertSee('کابل، چهارراهی قمبر');
    }

    public function test_only_users_who_may_see_the_order_get_the_slip(): void
    {
        $order = $this->order([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]]);
        $url = route('admin.packing-slip', ['order' => $order->uuid]);

        // بی ورود ← صفحه‌ی ورود
        $this->get($url)->assertRedirect();

        // پیکی که این سفارش به او واگذار نشده
        $courier = User::factory()->create(['role' => UserRole::Courier]);
        $this->actingAs($courier)->get($url)->assertForbidden();

        // ویرایشگر محتوا سفارش نمی‌بیند
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))->get($url)->assertForbidden();

        // پیکِ همین سفارش می‌بیند
        app(OrderWorkflow::class)->assignCourier($order, $courier);
        $this->actingAs($courier)->get($url)->assertOk();
    }

    public function test_view_order_has_the_print_button_except_for_cancelled_orders(): void
    {
        $this->actingAs($this->staff());
        $order = $this->order([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]]);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible(TestAction::make('packing_slip'));

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('reject'), ['reason' => 'مشتری نخواست']);

        Livewire::test(ViewOrder::class, ['record' => $order->refresh()->getKey()])
            ->assertActionHidden(TestAction::make('packing_slip'));
    }

    /** خطای پیدا‌شده در آزمون مرورگر: صفحه‌ی سفارشی که جنس فروشنده دارد در strict mode می‌شکست. */
    public function test_view_order_with_a_seller_item_opens_and_names_the_seller(): void
    {
        $this->actingAs($this->staff());
        $seller = $this->approvedSeller();
        $order = $this->order([
            [$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1],
            [$this->sellableVariant($seller, null, ['price' => '800.00', 'stock_qty' => 5]), 1],
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertOk()
            ->assertSee($seller->store_name);
    }
}
