<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use App\Services\Payments\CashCollection;
use App\Services\Payments\CashCollectionService;
use App\Support\Num;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۳ در پنل: دکمه‌ی «پول گرفته شد» (با کد تحویل)، واگذاری به پیک،
 * لیست پرداخت‌ها، تسویه‌ی پول پیک و رسید چاپی.
 */
class PaymentAdminTest extends TestCase
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
        $user = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($user);

        return $user;
    }

    private function order(): Order
    {
        return $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 2]]);
    }

    /**
     * یک سفارش که پولش گرفته شده (بی ورود به پنل؛ ثبت مستقیم با سرویس).
     *
     * @return array{0: Order, 1: Payment}
     */
    private function paidOrder(): array
    {
        $order = $this->order();
        $payment = app(CashCollectionService::class)->collect(
            $order,
            new CashCollection(deliveryCode: $order->delivery_code, amountReceived: $order->total),
            User::factory()->create(['role' => UserRole::Staff]),
        );

        return [$order->refresh(), $payment];
    }

    private function form(Order $order, array $overrides = []): array
    {
        return $overrides + [
            'delivery_code' => $order->delivery_code,
            'amount_received' => $order->total,
            'payer_name' => null,
            'note' => null,
            'mark_delivered' => true,
        ];
    }

    public function test_staff_collects_cash_from_the_order_page(): void
    {
        $staff = $this->staff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible(TestAction::make('collect_cash'))
            ->callAction(TestAction::make('collect_cash'), $this->form($order));

        $order->refresh();
        $payment = $order->payments->first();

        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame($staff->id, $payment->collected_by);
        $this->assertTrue($payment->delivery_code_verified);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
    }

    public function test_wrong_code_in_the_panel_changes_nothing(): void
    {
        $this->staff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('collect_cash'), $this->form($order, ['delivery_code' => '00000']));

        $order->refresh();
        $this->assertSame('awaiting_collection', $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
    }

    public function test_collect_button_is_hidden_after_the_money_is_taken(): void
    {
        $this->staff();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('collect_cash'), $this->form($order));

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden(TestAction::make('collect_cash'));
    }

    public function test_order_can_be_assigned_to_a_courier(): void
    {
        $this->staff();
        $order = $this->order();
        $courier = User::factory()->create(['role' => UserRole::Courier]);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction(TestAction::make('assign_courier'), ['courier_id' => $courier->id]);

        $this->assertSame($courier->id, app(OrderWorkflow::class)->courier($order->refresh())?->id);
    }

    public function test_courier_sees_only_his_own_orders(): void
    {
        $mine = $this->order();
        $other = $this->order();
        $courier = User::factory()->create(['role' => UserRole::Courier]);
        app(OrderWorkflow::class)->assignCourier($mine, $courier);

        $this->actingAs($courier);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);

        // سفارش دیگری برای پیک اصلاً وجود ندارد
        $this->get('/admin/orders/'.$other->id)->assertNotFound();
        $this->get('/admin/orders/'.$mine->id)->assertOk();
    }

    public function test_payments_list_shows_the_receipt_and_the_held_cash(): void
    {
        $courier = User::factory()->create(['role' => UserRole::Courier]);
        $order = $this->order();
        app(OrderWorkflow::class)->assignCourier($order, $courier);

        $payment = app(CashCollectionService::class)->collect(
            $order->refresh(),
            new CashCollection(deliveryCode: $order->delivery_code, amountReceived: $order->total),
            $courier,
        );

        $this->staff();

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$payment])
            ->assertSee(Num::toFa((string) $payment->receipt_number))
            ->assertSee(__('payments.courier_cash.held_by_courier'));
    }

    public function test_manager_takes_the_cash_from_the_courier(): void
    {
        $courier = User::factory()->create(['role' => UserRole::Courier]);
        $order = $this->order();
        app(OrderWorkflow::class)->assignCourier($order, $courier);
        $payment = app(CashCollectionService::class)->collect(
            $order->refresh(),
            new CashCollection(deliveryCode: $order->delivery_code, amountReceived: $order->total),
            $courier,
        );

        $manager = $this->staff();

        Livewire::test(ListPayments::class)
            ->selectTableRecords([$payment->getKey()])
            ->callAction(TestAction::make('handover')->table()->bulk());

        $payment->refresh();
        $this->assertSame(Payment::HANDED_OVER, $payment->courier_cash_status);
        $this->assertSame($manager->id, $payment->handed_over_to);
    }

    public function test_receipt_page_is_printable_for_staff_only(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('admin.receipt', ['payment' => $payment->uuid]))
            ->assertOk()
            ->assertSee(Num::toFa((string) $payment->receipt_number))
            ->assertSee(__('payments.receipt.code_verified'));

        // پیکی که این پول را نگرفته، رسیدش را نمی‌بیند
        $this->actingAs(User::factory()->create(['role' => UserRole::Courier]))
            ->get(route('admin.receipt', ['payment' => $payment->uuid]))
            ->assertForbidden();
    }

    public function test_receipt_needs_a_login(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->get(route('admin.receipt', ['payment' => $payment->uuid]))->assertRedirect();
    }

    public function test_customer_sees_the_receipt_number_on_the_order_page(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->get(route('order.show', ['uuid' => $order->uuid]))
            ->assertOk()
            ->assertSee(__('checkout.order.paid_heading'))
            ->assertSee(Num::toFa((string) $payment->receipt_number))
            // کد تحویل دیگر نشان داده نمی‌شود (کارش تمام شد)
            ->assertDontSee(Num::toFa((string) $order->delivery_code));
    }

    public function test_payments_cannot_be_created_or_deleted_from_the_panel(): void
    {
        $staff = $this->staff();
        $payment = $this->order()->payments->first();

        $this->assertFalse($staff->can('create', Payment::class));
        $this->assertFalse($staff->can('delete', $payment));
        $this->assertFalse(User::factory()->create(['role' => UserRole::Owner])->can('delete', $payment));
    }
}
