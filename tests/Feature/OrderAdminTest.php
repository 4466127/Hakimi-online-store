<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Num;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۲-۳: سفارش‌ها در پنل مدیریت — دیدن، تأیید، رد (لغو)، پیش بردن تا «تحویل شد»، یادداشت.
 */
class OrderAdminTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    private function asStaff(): User
    {
        $user = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($user);

        return $user;
    }

    private function order(array $variant = ['price' => '1000.00', 'stock_qty' => 5]): Order
    {
        return $this->placeOrder([[$this->sellableVariant(null, null, $variant), 2]]);
    }

    public function test_list_shows_orders_with_number_and_total(): void
    {
        $this->asStaff();
        $order = $this->order();

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->assertSee($order->number)
            ->assertSee('۲٬۲۰۰');                   // ۲×۱۰۰۰ + کرایه‌ی ۲۰۰
    }

    public function test_list_can_be_searched_by_phone_and_filtered_by_status(): void
    {
        $this->asStaff();
        $mine = $this->order();
        $other = $this->placeOrder(
            [[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]],
            ['purchaserName' => 'زینب', 'purchaserPhone' => '0788111222', 'recipientName' => 'زینب', 'recipientPhone' => '0788111222'],
        );

        Livewire::test(ListOrders::class)
            ->searchTable('0788111222')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$mine]);

        Livewire::test(ListOrders::class)
            ->filterTable('status', Order::STATUS_CONFIRMED)
            ->assertCanNotSeeTableRecords([$mine, $other]);
    }

    public function test_view_page_shows_delivery_code_items_and_customer(): void
    {
        $this->asStaff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSee($order->number)
            ->assertSee(Num::toFa((string) $order->delivery_code))
            ->assertSee($order->items->first()->product_name)
            ->assertSee('احمد');
    }

    public function test_staff_confirms_an_order(): void
    {
        $user = $this->asStaff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('confirm'));

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->sellerOrders->first()->status);

        // تاریخچه با نام کننده‌ی کار
        $this->assertTrue(OrderStatusHistory::query()
            ->where('subject_type', 'order')->where('subject_id', $order->id)
            ->where('to_status', Order::STATUS_CONFIRMED)->where('user_id', $user->id)->exists());
    }

    public function test_reject_needs_a_reason_and_frees_the_reserved_stock(): void
    {
        $this->asStaff();
        $order = $this->order();
        $variantId = $order->items->first()->variant_id;
        $this->assertSame(2, (int) ProductVariant::query()->whereKey($variantId)->value('reserved_qty'));

        // بی دلیل: فورم اجازه نمی‌دهد
        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('reject'), ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);
        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('reject'), ['reason' => 'مشتری تلفن را جواب نداد']);

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('مشتری تلفن را جواب نداد', $order->cancel_reason);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(Payment::FAILED, $order->payments->first()->status);

        // رزرو آزاد شد و موجودی دست‌نخورده ماند
        $variant = ProductVariant::query()->whereKey($variantId)->firstOrFail();
        $this->assertSame(0, (int) $variant->reserved_qty);
        $this->assertSame(5, (int) $variant->stock_qty);
    }

    public function test_full_flow_to_delivered_reduces_the_stock(): void
    {
        $this->asStaff();
        $order = $this->order();
        $variantId = $order->items->first()->variant_id;

        foreach (['confirm', 'process', 'ship', 'deliver'] as $action) {
            Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
                ->callAction(TestAction::make($action));
        }

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);

        $variant = ProductVariant::query()->whereKey($variantId)->firstOrFail();
        $this->assertSame(3, (int) $variant->stock_qty);        // ۵ − ۲
        $this->assertSame(0, (int) $variant->reserved_qty);
    }

    public function test_buttons_that_do_not_fit_the_status_are_hidden(): void
    {
        $this->asStaff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible(TestAction::make('confirm'))
            ->assertActionVisible(TestAction::make('reject'))
            ->assertActionHidden(TestAction::make('ship'))
            ->assertActionHidden(TestAction::make('deliver'));
    }

    public function test_admin_note_is_saved_and_logged(): void
    {
        $this->asStaff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('note'), ['admin_note' => '  بعد از ساعت ۴ تلفن کنید  ']);

        $this->assertSame('بعد از ساعت ۴ تلفن کنید', $order->refresh()->admin_note);
        $this->assertTrue(ActivityLog::query()->where('action', 'order.note_updated')->exists());
    }

    public function test_editor_cannot_open_orders_but_staff_can(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->id)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/orders/'.$order->id)->assertOk();
    }

    public function test_orders_cannot_be_deleted_from_the_panel(): void
    {
        $this->asStaff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionDoesNotExist(TestAction::make('delete'));

        $this->assertTrue(Order::query()->whereKey($order->id)->exists());
    }
}
