<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\PaymentEvents\PaymentEventResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Province;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Payments\OnlinePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * پرداخت آنلاین حساب‌پی از روی مستندات رسمی (مرحله‌ی ۵). همه‌ی درخواست‌ها به حساب‌پی ساختگی‌اند
 * (Http::fake) — هیچ پول واقعی و هیچ اتصال بیرونی در تست نیست.
 */
class HesabPayTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private const API = 'https://api-sandbox.hesab.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
        config(['services.hesabpay.api_key' => 'test-key', 'services.hesabpay.env' => 'sandbox', 'services.hesabpay.api_url' => null]);
        PaymentMethod::query()->where('code', PaymentMethod::HESABPAY)->update(['is_active' => true]);
    }

    /** حساب‌پی ساختگی: ساختن نشست و بررسی امضا (امضای «good» درست است). */
    private function fakeHesabPay(bool $sessionOk = true): void
    {
        Http::fake([
            self::API.'/api/v1/payment/create-session' => $sessionOk
                ? Http::response(['status_code' => 10, 'success' => true, 'message' => 'ok', 'session_id' => 'sess-1',
                    'url' => 'https://checkout.example/checkout/sess-1?data=enc', 'expires_at' => now()->addMinutes(15)->toIso8601String()])
                : Http::response(['success' => false, 'message' => 'Invalid request payload'], 400),
            self::API.'/api/v1/hesab/webhooks/verify-signature' => function (HttpRequest $request) {
                return Http::response(['success' => $request['signature'] === 'good'], 200);
            },
            self::API.'/api/v1/hesab/get/transaction-detail/*' => Http::response(['success' => true, 'data' => ['amount' => 1200, 'status' => 1]]),
        ]);
    }

    private function onlineOrder(string $price = '1000.00'): Order
    {
        return $this->placeOrder([[$this->sellableVariant(null, null, ['price' => $price, 'stock_qty' => 5]), 1]], [
            'paymentMethodCode' => PaymentMethod::HESABPAY,
        ]);
    }

    private function webhook(array $body): TestResponse
    {
        return $this->postJson('/webhooks/hesabpay', $body + ['signature' => 'good', 'timestamp' => '1707719607']);
    }

    private function success(Payment $payment, string $amount, string $txn = '0328379001707719668'): array
    {
        return ['status_code' => 10, 'success' => true, 'message' => 'Operation successful', 'sender_account' => '793111222',
            'transaction_id' => $txn, 'user_id' => $payment->uuid, 'amount' => (float) $amount, 'transaction_date' => '2026-11-24 11:04:28',
            'items' => [['id' => 'x', 'name' => 'y', 'price' => (float) $amount]]];
    }

    public function test_without_an_api_key_hesabpay_is_never_offered(): void
    {
        config(['services.hesabpay.api_key' => null]);
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 1]]);

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/checkout')->assertOk()
            ->assertDontSee('value="hesabpay"', false)
            ->assertSee(__('checkout.payment.online_soon'));

        // با کلید: روش آنلاین دیده می‌شود و «به‌زودی» نه
        config(['services.hesabpay.api_key' => 'test-key']);
        $this->withCookie(CartService::COOKIE, $cart->token)->get('/checkout')->assertOk()
            ->assertSee('value="hesabpay"', false)
            ->assertDontSee(__('checkout.payment.online_soon'));
    }

    public function test_checkout_creates_a_session_and_sends_the_customer_to_hesabpay(): void
    {
        $this->fakeHesabPay();
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1500.00', 'stock_qty' => 5]), 2]]);

        $this->withCookie(CartService::COOKIE, $cart->token)->post('/checkout', [
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل',
            'address_line' => 'کارته‌ی چهار',
            'delivery_date' => $this->firstDeliveryDate(),
            'payment_method_code' => 'hesabpay',
        ])->assertRedirect('https://checkout.example/checkout/sess-1?data=enc');

        $order = Order::query()->sole();
        $payment = Payment::query()->sole();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertSame('sess-1', $payment->gateway_session_id);
        $this->assertSame('hesabpay', $payment->provider);

        Http::assertSent(function (HttpRequest $request) use ($order, $payment): bool {
            return $request->url() === self::API.'/api/v1/payment/create-session'
                && $request->header('Authorization')[0] === 'API-KEY test-key'
                && $request['user_id'] === $payment->uuid
                && count($request['items']) === 1
                // یک قلم با مبلغ نهایی (اجناس + کرایه) تا جمع دقیقاً مبلغ سفارش باشد
                && (string) $request['items'][0]['price'] === (string) (float) $order->total
                && str_ends_with($request['redirect_failure_url'], '/payment/hesabpay/'.$payment->uuid.'/failed');
        });
    }

    public function test_if_hesabpay_does_not_open_the_order_page_offers_retry_and_cash(): void
    {
        $this->fakeHesabPay(sessionOk: false);
        $order = $this->onlineOrder();

        $this->post(route('order.pay', ['uuid' => $order->uuid]))
            ->assertRedirect(route('order.show', ['uuid' => $order->uuid]))
            ->assertSessionHas('error', __('payments.errors.gateway_error'));

        $this->get(route('order.show', ['uuid' => $order->uuid]))->assertOk()
            ->assertSee(__('payments.online.pay_button'))
            ->assertSee(__('payments.online.switch_button'));
    }

    public function test_only_a_signed_webhook_marks_the_order_paid_and_repeats_have_no_effect(): void
    {
        $this->fakeHesabPay();
        $order = $this->onlineOrder();
        $payment = Payment::query()->sole();

        // برگشت مرورگر «موفق» هیچ‌چیز را پرداخت‌شده نمی‌کند
        $this->get(route('payment.hesabpay.return', ['payment' => $payment->uuid]))->assertRedirect();
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);

        // امضای نادرست: ۴۰۱ و بی تغییر
        $this->postJson('/webhooks/hesabpay', $this->success($payment, $order->total) + ['signature' => 'forged', 'timestamp' => '1'])->assertStatus(401);
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);
        $this->assertSame(0, PaymentEvent::query()->count());

        // امضای درست
        $this->webhook($this->success($payment, $order->total))->assertOk()->assertJson(['success' => true, 'outcome' => 'paid']);
        $payment->refresh();
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame('0328379001707719668', $payment->provider_reference);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNull(PaymentEvent::query()->sole()->payload['signature'] ?? null);

        // همان پیام دوباره (حساب‌پی تا ۵ بار با امضای تازه می‌فرستد): ۲۰۰، بی اثر
        $this->webhook($this->success($payment, $order->total))->assertOk();
        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, Payment::query()->where('status', Payment::PAID)->count());

        $this->get(route('order.show', ['uuid' => $order->uuid]))->assertOk()
            ->assertSee(__('checkout.order.paid_heading'))
            ->assertDontSee(__('payments.online.pay_button'));
    }

    public function test_portal_validation_ping_is_acknowledged_without_touching_orders(): void
    {
        $this->fakeHesabPay();
        $this->onlineOrder();

        $this->webhook(['type' => 'webhook.validation', 'validation_id' => 'v-1', 'account' => '776147799', 'event_type' => 'payment_success', 'test' => true])
            ->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(0, PaymentEvent::query()->count());
        $this->assertSame(Payment::PENDING, Payment::query()->sole()->status);
    }

    public function test_wrong_amount_or_unknown_payment_is_not_paid_and_flagged_for_review(): void
    {
        $this->fakeHesabPay();
        $order = $this->onlineOrder();
        $payment = Payment::query()->sole();

        $this->webhook($this->success($payment, '10.00', 'txn-small'))->assertOk();
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);
        $this->assertSame(PaymentEvent::AMOUNT_MISMATCH, PaymentEvent::query()->where('transaction_id', 'txn-small')->value('outcome'));

        $this->webhook(['user_id' => '00000000-0000-4000-8000-000000000999'] + $this->success($payment, $order->total, 'txn-other'))->assertOk();
        $this->assertSame(PaymentEvent::UNKNOWN_PAYMENT, PaymentEvent::query()->where('transaction_id', 'txn-other')->value('outcome'));
        $this->assertSame(2, PaymentEvent::query()->needsReview()->count());
    }

    public function test_failure_keeps_the_order_waiting_and_never_downgrades_a_paid_one(): void
    {
        $this->fakeHesabPay();
        $order = $this->onlineOrder();
        $payment = Payment::query()->sole();
        $failure = ['success' => false, 'status' => 'failed', 'reason' => 'payment_declined', 'message' => 'rejected', 'status_code' => null,
            'session_id' => 'sess-1', 'transaction_id' => null, 'user_id' => $payment->uuid, 'payment_method' => 'afpay', 'failure_stage' => 'afpay_payment', 'amount' => 65];

        $this->webhook($failure)->assertOk();
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);
        $this->assertSame('payment_declined', $payment->fresh()->failed_reason);

        $this->webhook($this->success($payment, $order->total))->assertOk();
        // دیرتر یک رد دیگر (با دلیل دیگر) — پرداخت‌شده پایین نمی‌آید
        $this->webhook(['reason' => 'card_payment_failed'] + $failure)->assertOk();
        $this->assertSame(Payment::PAID, $payment->fresh()->status);
    }

    public function test_browser_failure_return_only_shows_a_safe_message(): void
    {
        $this->fakeHesabPay();
        $order = $this->onlineOrder();
        $payment = Payment::query()->sole();
        $data = json_encode(['success' => false, 'status' => 'cancelled', 'reason' => 'session_expired',
            'message' => '<script>alert(1)</script>Session expired', 'session_id' => 'sess-1', 'transaction_id' => null, 'user_id' => $payment->uuid]);

        $this->get(route('payment.hesabpay.failed', ['payment' => $payment->uuid, 'data' => $data]))
            ->assertRedirect(route('order.show', ['uuid' => $order->uuid]));
        $this->get(route('order.show', ['uuid' => $order->uuid]))->assertOk()
            ->assertSee('Session expired')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(Payment::PENDING, $payment->fresh()->status);
    }

    public function test_customer_can_switch_to_cash_and_a_late_payment_is_flagged(): void
    {
        $this->fakeHesabPay();
        $order = $this->onlineOrder();
        $online = Payment::query()->sole();

        $this->post(route('order.pay.cash', ['uuid' => $order->uuid]))->assertSessionHas('status', __('payments.online.switched'));
        $order->refresh();
        $this->assertSame(PaymentMethod::COD, $order->payment_method_code);
        $this->assertSame('awaiting_collection', $order->payment_status);
        $this->assertSame(Payment::FAILED, $online->fresh()->status);
        $this->assertSame(Payment::AWAITING_COLLECTION, Payment::query()->where('attempt_no', 2)->value('status'));

        // پول آنلاین بعداً رسید: پرداخت‌شده نمی‌شود؛ مدیر باید پس بدهد
        $this->webhook($this->success($online, $order->total))->assertOk();
        $this->assertSame(PaymentEvent::PAID_ON_CLOSED, PaymentEvent::query()->sole()->outcome);
        $this->assertTrue(PaymentEvent::query()->sole()->needs_review);
    }

    public function test_unpaid_online_orders_are_cancelled_and_stock_released(): void
    {
        $this->fakeHesabPay();
        config(['store.payments.online_cancel_after_minutes' => 120]);
        $order = $this->onlineOrder();
        $cash = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 5]), 1]]);

        $this->travel(119)->minutes();
        $this->assertSame(0, app(OnlinePaymentService::class)->cancelStale());

        $this->travel(2)->minutes();
        $this->assertSame(1, app(OnlinePaymentService::class)->cancelStale());
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $cash->fresh()->status);   // نقد دست نمی‌خورد
        $this->assertTrue(StockMovement::query()->where('type', 'release')->exists());
    }

    public function test_only_owner_and_manager_see_gateway_messages_and_can_mark_reviewed(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
            ->get(PaymentEventResource::getUrl('index'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(PaymentEventResource::getUrl('index'))->assertForbidden();
    }
}
