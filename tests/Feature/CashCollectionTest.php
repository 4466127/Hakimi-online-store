<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Orders\CheckoutPage;
use App\Services\Orders\OrderWorkflow;
use App\Services\Payments\CashCollection;
use App\Services\Payments\CashCollectionService;
use App\Services\Payments\Gateways\PaymentGatewayManager;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentStateMachine;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۳: گرفتن پول نقد با **بررسی کد تحویل**، رسید، اجازه‌ها، و پول دست پیک.
 */
class CashCollectionTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    private function cash(): CashCollectionService
    {
        return app(CashCollectionService::class);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function courier(): User
    {
        return User::factory()->create(['role' => UserRole::Courier]);
    }

    /** سفارش درِ خانه‌ی آماده‌ی تحویل (۲ جنس × ۱۰۰۰ + کرایه). */
    private function order(array $overrides = []): Order
    {
        if (($overrides['deliveryMethod'] ?? null) === 'pickup') {
            app(Settings::class)->set('delivery.pickup_enabled', true);
            app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        }

        return $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 2]], $overrides);
    }

    private function data(Order $order, array $overrides = []): CashCollection
    {
        return new CashCollection(...($overrides + [
            'deliveryCode' => $order->delivery_code,
            'amountReceived' => $order->total,
        ]));
    }

    // ───────────────────────── کد تحویل ─────────────────────────

    public function test_cash_is_recorded_with_the_right_delivery_code_and_a_receipt_is_made(): void
    {
        $order = $this->order();
        $staff = $this->staff();

        $payment = $this->cash()->collect($order, $this->data($order), $staff);

        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame($order->total, $payment->amount_received);
        $this->assertTrue($payment->delivery_code_verified);
        $this->assertSame($staff->id, $payment->collected_by);
        $this->assertSame(Payment::AT_DOOR, $payment->collected_at_type);
        $this->assertMatchesRegularExpression('/^RC-\d{6}$/', (string) $payment->receipt_number);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame($order->total, $order->paid_total);
        // همراه گرفتن پول، جنس هم تحویل داده شد
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
    }

    public function test_wrong_delivery_code_records_nothing(): void
    {
        $order = $this->order();

        try {
            $this->cash()->collect($order, $this->data($order, ['deliveryCode' => '00000']), $this->staff());
            $this->fail('کد غلط باید رد شود');
        } catch (PaymentException $e) {
            $this->assertSame('delivery_code_wrong', $e->reason);
        }

        $order->refresh();
        $this->assertSame('awaiting_collection', $order->payment_status);
        $this->assertSame('0.00', $order->paid_total);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Payment::AWAITING_COLLECTION, $order->payments->first()->status);
        // تلاش نادرست سند دارد
        $this->assertTrue(ActivityLog::query()->where('action', 'payment.delivery_code_wrong')->exists());
    }

    public function test_delivery_code_is_required_and_persian_digits_are_accepted(): void
    {
        $order = $this->order();

        try {
            $this->cash()->collect($order, $this->data($order, ['deliveryCode' => '  ']), $this->staff());
            $this->fail('کد خالی باید رد شود');
        } catch (PaymentException $e) {
            $this->assertSame('delivery_code_required', $e->reason);
        }

        // همان کد با ارقام فارسی
        $persian = strtr((string) $order->delivery_code, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        $payment = $this->cash()->collect($order, $this->data($order, ['deliveryCode' => $persian]), $this->staff());

        $this->assertSame(Payment::PAID, $payment->status);
    }

    // ───────────────────────── مبلغ ─────────────────────────

    public function test_a_different_amount_needs_a_reason(): void
    {
        $order = $this->order();

        try {
            $this->cash()->collect($order, $this->data($order, ['amountReceived' => '2000.00']), $this->staff());
            $this->fail('مبلغ ناجور بی دلیل باید رد شود');
        } catch (PaymentException $e) {
            $this->assertSame('reason_required', $e->reason);
        }

        $payment = $this->cash()->collect($order, $this->data($order, [
            'amountReceived' => '2000.00',
            'note' => '۲۰۰ افغانی کم داشت، اجازه دادیم',
        ]), $this->staff());

        $this->assertSame('2000.00', $payment->amount_received);
        $this->assertSame('۲۰۰ افغانی کم داشت، اجازه دادیم', $payment->note);
        // کمتر از مبلغ سفارش ← «بخشی پرداخت شد»
        $this->assertSame('partially_paid', $order->refresh()->payment_status);
    }

    public function test_zero_amount_is_refused(): void
    {
        $order = $this->order();

        $this->expectException(PaymentException::class);
        $this->cash()->collect($order, $this->data($order, ['amountReceived' => '0', 'note' => 'هیچ']), $this->staff());
    }

    // ───────────────────────── اجازه‌ها ─────────────────────────

    public function test_courier_can_only_collect_for_his_own_order(): void
    {
        $order = $this->order();
        $courier = $this->courier();
        $otherCourier = $this->courier();

        app(OrderWorkflow::class)->assignCourier($order, $courier);

        try {
            $this->cash()->collect($order, $this->data($order), $otherCourier);
            $this->fail('پیک دیگری نباید بتواند');
        } catch (PaymentException $e) {
            $this->assertSame('not_your_order', $e->reason);
        }

        $payment = $this->cash()->collect($order->refresh(), $this->data($order), $courier);
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame($courier->id, $payment->collected_by);
    }

    public function test_courier_cannot_collect_at_a_collection_point(): void
    {
        $order = $this->order(['deliveryMethod' => 'pickup']);
        $courier = $this->courier();
        app(OrderWorkflow::class)->assignCourier($order, $courier);

        try {
            $this->cash()->collect($order->refresh(), $this->data($order), $courier);
            $this->fail('پیک در نمایندگی نباید پول بگیرد');
        } catch (PaymentException $e) {
            $this->assertSame('courier_door_only', $e->reason);
        }
    }

    public function test_editor_cannot_collect_cash(): void
    {
        $order = $this->order();

        try {
            $this->cash()->collect($order, $this->data($order), User::factory()->create(['role' => UserRole::Editor]));
            $this->fail('ویرایشگر نباید بتواند');
        } catch (PaymentException $e) {
            $this->assertSame('not_allowed', $e->reason);
        }
    }

    // ───────────────────────── وضعیت سفارش و تکرار ─────────────────────────

    public function test_cancelled_order_takes_no_money(): void
    {
        $order = $this->order();
        app(OrderWorkflow::class)->cancel($order, 'مشتری جواب نداد', $this->staff());

        try {
            $this->cash()->collect($order->refresh(), $this->data($order), $this->staff());
            $this->fail('سفارش لغو‌شده پول نمی‌گیرد');
        } catch (PaymentException $e) {
            $this->assertSame('order_closed', $e->reason);
        }
    }

    public function test_money_is_not_taken_twice(): void
    {
        $order = $this->order();
        $this->cash()->collect($order, $this->data($order), $this->staff());

        try {
            $this->cash()->collect($order->refresh(), $this->data($order), $this->staff());
            $this->fail('دو بار نباید ثبت شود');
        } catch (PaymentException $e) {
            $this->assertSame('already_paid', $e->reason);
        }

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->where('status', Payment::PAID)->count());
    }

    public function test_without_delivery_the_order_stays_but_money_is_recorded(): void
    {
        $order = $this->order();

        $payment = $this->cash()->collect($order, $this->data($order, ['markDelivered' => false]), $this->staff());

        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_delivery_reduces_the_stock(): void
    {
        $order = $this->order();
        $variantId = $order->items->first()->variant_id;

        $this->cash()->collect($order, $this->data($order), $this->staff());

        $variant = ProductVariant::query()->whereKey($variantId)->firstOrFail();
        $this->assertSame(3, (int) $variant->stock_qty);        // ۵ − ۲
        $this->assertSame(0, (int) $variant->reserved_qty);
    }

    // ───────────────────────── نمایندگی و پول دست پیک ─────────────────────────

    public function test_cash_at_a_collection_point_is_not_courier_cash(): void
    {
        $order = $this->order(['deliveryMethod' => 'pickup']);

        $payment = $this->cash()->collect($order, $this->data($order), $this->staff());

        $this->assertSame(Payment::AT_POINT, $payment->collected_at_type);
        $this->assertNotNull($payment->collection_point_id);
        $this->assertNull($payment->courier_cash_status);
    }

    public function test_courier_cash_is_held_until_it_is_handed_over(): void
    {
        $order = $this->order();
        $courier = $this->courier();
        app(OrderWorkflow::class)->assignCourier($order, $courier);

        $payment = $this->cash()->collect($order->refresh(), $this->data($order), $courier);
        $this->assertSame(Payment::HELD_BY_COURIER, $payment->courier_cash_status);

        $held = $this->cash()->heldByCourier();
        $this->assertCount(1, $held);
        $this->assertSame($courier->id, $held->first()['courier']->id);
        $this->assertSame($order->total, $held->first()['total']);

        $manager = $this->staff();
        $this->assertSame(1, $this->cash()->handOver([$payment], $manager));

        $payment->refresh();
        $this->assertSame(Payment::HANDED_OVER, $payment->courier_cash_status);
        $this->assertSame($manager->id, $payment->handed_over_to);
        $this->assertNotNull($payment->handed_over_at);
        $this->assertTrue(ActivityLog::query()->where('action', 'payment.cash_handed_over')->exists());
        $this->assertTrue($this->cash()->heldByCourier()->isEmpty());

        // بار دوم چیزی برای تسویه نیست
        try {
            $this->cash()->handOver([$payment], $manager);
            $this->fail('دو بار تسویه نباید شود');
        } catch (PaymentException $e) {
            $this->assertSame('nothing_to_hand_over', $e->reason);
        }
    }

    // ───────────────────────── ماشین وضعیت پرداخت ─────────────────────────

    public function test_failed_payment_cannot_become_paid(): void
    {
        $order = $this->order();
        $payment = $order->payments->first();
        $machine = app(PaymentStateMachine::class);

        $machine->fail($payment, 'customer_refused', $this->staff());
        $this->assertSame(Payment::FAILED, $payment->refresh()->status);
        $this->assertSame('failed', $order->refresh()->payment_status);

        try {
            $machine->markPaid($payment, $order->total);
            $this->fail('پرداخت ناموفق دوباره پرداخت‌شده نمی‌شود');
        } catch (PaymentException $e) {
            $this->assertSame('not_collectable', $e->reason);
        }
    }

    // ───────────────────────── درگاه آنلاین (حساب‌پی، مرحله‌ی ۵) ─────────────────────────

    public function test_online_gateway_is_not_ready_yet(): void
    {
        $gateways = app(PaymentGatewayManager::class);
        $hesabpay = PaymentMethod::query()->where('code', PaymentMethod::HESABPAY)->firstOrFail();

        $this->assertFalse($gateways->isReady($hesabpay));
        $this->assertFalse($gateways->for($hesabpay)->isReady());
        $this->assertTrue($gateways->isReady(PaymentMethod::query()->where('code', PaymentMethod::COD)->firstOrFail()));
    }

    public function test_an_active_online_gateway_is_still_not_offered_until_it_works(): void
    {
        // اگر مدیر اشتباهی حساب‌پی را روشن کند، باز هم مشتری نمی‌تواند با آن سفارش بدهد
        PaymentMethod::query()->where('code', PaymentMethod::HESABPAY)->update(['is_active' => true]);

        $codes = app(CheckoutPage::class)->paymentMethods()
            ->map(fn (array $row): string => $row['method']->code)->all();

        $this->assertNotContains(PaymentMethod::HESABPAY, $codes);
    }
}
