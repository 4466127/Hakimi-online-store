<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\StockMovement;
use App\Services\Orders\OrderStateMachine;
use App\Services\Orders\OrderTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۲-۱: ماشین وضعیت سفارش (گذارهای مجاز، اثر روی موجودی، وضعیت والد، لغو جزئی، تاریخچه).
 */
class OrderStateMachineTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private OrderStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
        $this->machine = app(OrderStateMachine::class);
    }

    private function walk(SellerOrder $so, string ...$statuses): SellerOrder
    {
        foreach ($statuses as $status) {
            $so = $this->machine->transition($so, $status);
        }

        return $so;
    }

    public function test_full_happy_path_updates_order_stock_and_history(): void
    {
        $variant = $this->sellableVariant(null, null, ['stock_qty' => 10]);
        $order = $this->placeOrder([[$variant, 3]]);
        $so = $order->sellerOrders[0];
        $this->assertSame('3.000', $variant->fresh()->reserved_qty);

        $so = $this->walk($so, 'confirmed', 'processing', 'shipped');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->assertSame('3.000', $variant->fresh()->reserved_qty, 'تا تحویل، موجودی همچنان رزرو است');

        $so = $this->walk($so, 'delivered');

        $fresh = $order->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNotNull($so->delivered_at);
        $this->assertSame('7.000', $variant->fresh()->stock_qty);          // ۱۰ − ۳
        $this->assertSame('0.000', $variant->fresh()->reserved_qty);
        $sale = StockMovement::query()->where('type', 'sale')->firstOrFail();
        $this->assertSame('-3.000', $sale->qty_delta);

        // هر گذار در تاریخچه، هم برای سفارش فروشنده و هم والد
        $this->assertSame(
            ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            OrderStatusHistory::query()->where('subject_type', 'seller_order')->orderBy('id')->pluck('to_status')->all(),
        );
        $this->assertSame(
            ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            OrderStatusHistory::query()->where('subject_type', 'order')->orderBy('id')->pluck('to_status')->all(),
        );
        // «تحویل شد» بی گرفتن پول، پرداخت را «پرداخت‌شده» نمی‌کند
        $this->assertSame('awaiting_collection', $fresh->payment_status);
        $this->assertSame('0.00', $fresh->paid_total);
    }

    public function test_forbidden_transitions_are_rejected_and_change_nothing(): void
    {
        $order = $this->placeOrder([[$this->sellableVariant(null), 1]]);
        $so = $order->sellerOrders[0];

        foreach ([['pending', 'shipped'], ['pending', 'delivered'], ['pending', 'processing'], ['pending', 'returned']] as [$from, $to]) {
            try {
                $this->machine->transition($so, $to);
                $this->fail("$from → $to نباید مجاز باشد");
            } catch (OrderTransitionException $e) {
                $this->assertSame('transition_denied', $e->reason);
                $this->assertNotSame('', $e->userMessage());
            }
        }
        $this->assertSame('pending', $so->fresh()->status);

        $so = $this->walk($so, 'confirmed', 'processing', 'shipped', 'delivered');
        $this->expectException(OrderTransitionException::class);
        $this->machine->transition($so, 'confirmed');                      // برگشت به عقب ممکن نیست
    }

    public function test_cancelled_and_returned_are_final(): void
    {
        $so = $this->placeOrder([[$this->sellableVariant(null), 1]])->sellerOrders[0];
        $so = $this->machine->transition($so, 'cancelled', null, 'مشتری نخواست');

        foreach (['pending', 'confirmed', 'cancelled', 'delivered'] as $to) {
            $this->assertFalse($this->machine->can('cancelled', $to));
        }
        $this->assertSame([], $this->machine->next('returned'));
        $this->assertSame('cancelled', $so->status);
    }

    public function test_cancel_needs_a_reason(): void
    {
        $so = $this->placeOrder([[$this->sellableVariant(null), 1]])->sellerOrders[0];

        foreach ([null, '', '   '] as $note) {
            try {
                $this->machine->transition($so, 'cancelled', null, $note);
                $this->fail('لغو بی دلیل نباید ممکن باشد');
            } catch (OrderTransitionException $e) {
                $this->assertSame('reason_required', $e->reason);
            }
        }
        $this->assertSame('pending', $so->fresh()->status);
    }

    public function test_cancel_releases_the_reservation_and_fails_the_open_payment(): void
    {
        $variant = $this->sellableVariant(null, null, ['stock_qty' => 5]);
        $order = $this->placeOrder([[$variant, 2]]);
        $this->assertSame('2.000', $variant->fresh()->reserved_qty);

        $this->machine->transition($order->sellerOrders[0], 'cancelled', null, 'مشتری نخواست');

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('مشتری نخواست', $fresh->cancel_reason);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame('0.000', $variant->fresh()->reserved_qty);
        $this->assertSame('5.000', $variant->fresh()->stock_qty, 'موجودی فروش نرفته دست نخورد');
        $this->assertSame(2, $fresh->items[0]->qty_cancelled);
        $this->assertSame('release', StockMovement::query()->latest('id')->firstOrFail()->type);

        $payment = $fresh->payments[0];
        $this->assertSame('failed', $payment->status);
        $this->assertSame('cancelled', $payment->failed_reason);
        $this->assertSame('failed', $fresh->payment_status);
        $this->assertSame('0.00', $fresh->paid_total, 'هیچ پولی ثبت نشد');
    }

    public function test_cancelling_one_seller_keeps_the_others_and_recalculates_the_amount_to_collect(): void
    {
        $a = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5), ['price' => '3000.00']);
        $b = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(8), ['price' => '4000.00']);
        $c = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(3), ['price' => '2000.00']);
        $order = $this->placeOrder([[$a, 2], [$b, 1], [$c, 1]]);
        $this->assertSame('12200.00', $order->total);
        [$soA, $soB, $soC] = $order->sellerOrders->all();

        $this->machine->transition($soB, 'cancelled', null, 'جنس فروشنده B تمام شد');

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status, 'سفارش والد لغو نشد؛ دو فروشنده‌ی دیگر مانده');
        $this->assertSame('8000.00', $fresh->subtotal);
        $this->assertSame('8200.00', $fresh->total);                       // ۸٬۰۰۰ + کرایه‌ی ۲۰۰
        $this->assertSame('8200.00', $fresh->payments[0]->amount, 'مبلغی که پیک باید بگیرد کم شد');
        $this->assertSame('awaiting_collection', $fresh->payments[0]->status);
        $this->assertSame('cancelled', $soB->fresh()->status);
        $this->assertSame('pending', $soA->fresh()->status);
        $this->assertSame('0.000', $b->fresh()->reserved_qty);
        $this->assertSame('2.000', $a->fresh()->reserved_qty);             // رزرو A و C سر جایش
        $this->assertSame('1.000', $c->fresh()->reserved_qty);
        // صورت مالی فروشنده‌ی لغوشده برای ردیابی می‌ماند (دفتر فقط «تحویل‌شده + پول‌رسیده» را می‌خواند)
        $this->assertSame('4000.00', $soB->fresh()->gross_subtotal);

        // آخرین فروشنده‌ها هم لغو شدند ← والد لغو
        $this->machine->transition($soA, 'cancelled', null, 'مشتری نخواست');
        $this->assertSame('pending', $order->fresh()->status);
        $this->machine->transition($soC, 'cancelled', null, 'مشتری نخواست');
        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('failed', $fresh->payments[0]->status);
    }

    public function test_parent_status_follows_the_slowest_seller_order(): void
    {
        $a = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5));
        $b = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5));
        $order = $this->placeOrder([[$a, 1], [$b, 1]]);
        [$soA, $soB] = $order->sellerOrders->all();

        $this->walk($soA, 'confirmed', 'processing', 'shipped', 'delivered');
        $this->assertSame('pending', $order->fresh()->status, 'فروشنده‌ی B هنوز pending است');
        $this->assertNull($order->fresh()->delivered_at);

        $this->walk($soB, 'confirmed');
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->walk($soB->fresh(), 'processing', 'shipped');
        $this->assertSame('shipped', $order->fresh()->status);

        $this->walk($soB->fresh(), 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_total_is_not_rewritten_after_money_was_received(): void
    {
        $a = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5), ['price' => '3000.00']);
        $b = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5), ['price' => '1000.00']);
        $order = $this->placeOrder([[$a, 1], [$b, 1]]);
        $order->forceFill(['paid_total' => '4200.00', 'payment_status' => 'paid'])->save();   // پول قبلاً رسیده (مرحله‌ی ۳ این را می‌گذارد)

        $this->machine->transition($order->sellerOrders[1], 'cancelled', null, 'ناموجود');

        $fresh = Order::query()->findOrFail($order->id);
        $this->assertSame('4200.00', $fresh->total, 'برگشت پول فرایند بازپرداخت دارد؛ جمع بی صدا عوض نمی‌شود');
        $this->assertSame('pending', $fresh->status);
    }

    public function test_transition_is_logged_in_activity_log(): void
    {
        $order = $this->placeOrder([[$this->sellableVariant(null), 1]]);
        $this->machine->transition($order->sellerOrders[0], 'confirmed');

        $this->assertDatabaseHas('activity_log', ['action' => 'seller_order.status_changed']);
        $this->assertDatabaseHas('activity_log', ['action' => 'order.status_changed']);
        $this->assertDatabaseHas('activity_log', ['action' => 'order.placed']);
    }
}
