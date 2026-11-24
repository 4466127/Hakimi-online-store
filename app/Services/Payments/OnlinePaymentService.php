<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderWorkflow;
use App\Services\Payments\Gateways\GatewayEvent;
use App\Services\Payments\Gateways\PaymentGatewayManager;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * پرداخت آنلاین (حساب‌پی) از شروع تا تأیید (مرحله‌ی ۵).
 *
 * قاعده‌ها (پروژه + مستندات حساب‌پی):
 * - **فقط پیام سرور-به-سرورِ امضاشده** پرداخت را «پرداخت‌شده» می‌کند؛ برگشت مرورگر هرگز.
 * - هر پیام یک بار (`payment_events` یکتا)؛ تکرار با HTTP 200 بی اثر پذیرفته می‌شود.
 * - مبلغ پیام باید دقیقاً مبلغ پرداخت باشد؛ وگرنه پرداخت‌شده نمی‌شود و برای مدیر علامت می‌خورد.
 * - پیام رد، پرداخت‌شده را هرگز پایین نمی‌آورد؛ پرداخت منتظر می‌ماند تا مشتری دوباره بپردازد.
 * - پولی که برای سفارش لغوشده یا پرداخت‌شده‌ی دیگر رسید، فقط «نیاز به بررسی» می‌شود (مدیر پس می‌دهد).
 */
class OnlinePaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentStateMachine $payments,
        private readonly OrderWorkflow $workflow,
        private readonly ActivityLogger $logger,
    ) {}

    /** پرداخت آنلاینِ منتظرِ این سفارش (اگر هست). */
    public function pendingPayment(Order $order): ?Payment
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('status', Payment::PENDING)
            ->whereIn('method_code', $this->onlineCodes())
            ->latest('id')
            ->first();
    }

    /**
     * نشست پرداخت را می‌سازد (یا نشست هنوز معتبر را دوباره می‌دهد) و آدرس صفحه‌ی حساب‌پی را برمی‌گرداند.
     *
     * @throws PaymentException
     */
    public function begin(Payment $payment): string
    {
        $payment->refresh();
        $order = Order::query()->findOrFail($payment->order_id);
        if ($payment->status !== Payment::PENDING || $order->status === Order::STATUS_CANCELLED) {
            throw new PaymentException($payment->status === Payment::PAID ? 'already_paid' : 'not_collectable');
        }
        $method = PaymentMethod::query()->where('code', $payment->method_code)->firstOrFail();
        $gateway = $this->gateways->for($method);

        // نشستی که هنوز دست‌کم یک دقیقه وقت دارد دوباره استفاده می‌شود (دو کلیک = دو نشست نشود)
        if ($payment->gateway_checkout_url !== null && $payment->expires_at !== null && $payment->expires_at->gt(now()->addMinute())) {
            return $payment->gateway_checkout_url;
        }

        $start = $gateway->start($payment, $order);

        DB::transaction(function () use ($payment, $start): void {
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $row->forceFill([
                'provider' => $row->method_code,
                'gateway_session_id' => $start->reference,
                'gateway_checkout_url' => mb_substr($start->redirectUrl, 0, 1000),
                'expires_at' => $start->expiresAt,
                'failed_reason' => null,
            ])->save();
        });
        $this->logger->log('payment.online_started', $payment, ['order' => $order->number, 'session' => $start->reference]);

        return $start->redirectUrl;
    }

    /** پیام تأییدشده‌ی درگاه را یک بار اعمال می‌کند. */
    public function handle(string $provider, GatewayEvent $event): PaymentEvent
    {
        try {
            return DB::transaction(fn (): PaymentEvent => $this->apply($provider, $event));
        } catch (QueryException $e) {
            // دو پیام هم‌زمان با یک شناسه: دومی به یکتایی می‌خورد و «تکرار» است
            $existing = PaymentEvent::query()->where('provider', $provider)->where('event_id', $event->eventId)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    private function apply(string $provider, GatewayEvent $event): PaymentEvent
    {
        if (PaymentEvent::query()->where('provider', $provider)->where('event_id', $event->eventId)->lockForUpdate()->exists()) {
            return new PaymentEvent(['outcome' => PaymentEvent::DUPLICATE]);
        }

        $payment = $event->reference === '' ? null : Payment::query()->where('uuid', $event->reference)->first();
        if ($payment !== null) {
            // ترتیب ثابت قفل: سفارش، بعد پرداخت (مثل PaymentStateMachine)
            Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
        }

        [$outcome, $review] = $event->isPaid() ? $this->applyPaid($payment, $event) : $this->applyFailed($payment, $event);

        $record = PaymentEvent::create([
            'provider' => $provider,
            'event_id' => $event->eventId,
            'event_type' => $event->isPaid() ? 'payment_success' : 'payment_failure',
            'payment_id' => $payment?->id,
            'reference' => $event->reference === '' ? null : $event->reference,
            'transaction_id' => $event->transactionId,
            'amount' => $event->amount,
            'outcome' => $outcome,
            'needs_review' => $review,
            'payload' => $event->raw,
        ]);

        $this->logger->log('payment.gateway_event', $payment ?? $record, [
            'provider' => $provider,
            'outcome' => $outcome,
            'transaction' => $event->transactionId,
            'amount' => $event->amount,
        ]);
        if ($review) {
            Log::warning('payment.gateway_event_needs_review', ['event' => $record->id, 'outcome' => $outcome, 'payment' => $payment?->id]);
        }

        return $record;
    }

    /** @return array{0: string, 1: bool} نتیجه و «نیاز به بررسی» */
    private function applyPaid(?Payment $payment, GatewayEvent $event): array
    {
        if ($payment === null) {
            return [PaymentEvent::UNKNOWN_PAYMENT, true];
        }
        if ($payment->status === Payment::PAID) {
            return $payment->provider_reference === $event->transactionId
                ? [PaymentEvent::DUPLICATE, false]
                : [PaymentEvent::DOUBLE_PAYMENT, true];
        }
        if ($payment->status !== Payment::PENDING) {
            return [PaymentEvent::PAID_ON_CLOSED, true];
        }
        if ($event->amount === null || Money::compare($event->amount, $payment->amount) !== 0) {
            return [PaymentEvent::AMOUNT_MISMATCH, true];
        }

        $this->payments->markPaid($payment, $event->amount, [
            'provider' => $payment->method_code,
            'provider_reference' => $event->transactionId,
            'collected_at' => now(),
        ], null, (string) __('payments.online.paid_note', ['transaction' => $event->transactionId]));

        return [PaymentEvent::PAID, false];
    }

    /** @return array{0: string, 1: bool} */
    private function applyFailed(?Payment $payment, GatewayEvent $event): array
    {
        if ($payment === null || $payment->status !== Payment::PENDING) {
            return [PaymentEvent::IGNORED, false];
        }
        $payment->forceFill(['failed_reason' => mb_substr($event->reason ?? 'payment_failed', 0, 30)])->save();

        return [PaymentEvent::FAILURE_RECORDED, false];
    }

    /**
     * مشتری به‌جای پرداخت آنلاین، نقد می‌پردازد (درِ خانه → پیک؛ حضوری → نمایندگی).
     *
     * @throws PaymentException
     */
    public function switchToCash(Order $order): Payment
    {
        $code = $order->delivery_method === 'pickup' ? PaymentMethod::CASH_POINT : PaymentMethod::COD;
        $method = PaymentMethod::query()->active()->where('code', $code)->first();
        if ($method === null) {
            throw new PaymentException('cash_unavailable');
        }

        return DB::transaction(function () use ($order, $method): Payment {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $old = $this->pendingPayment($locked);
            if ($old === null || $locked->status === Order::STATUS_CANCELLED) {
                throw new PaymentException('not_collectable');
            }
            $old = Payment::query()->whereKey($old->id)->lockForUpdate()->firstOrFail();

            $old->forceFill(['status' => Payment::FAILED, 'failed_reason' => 'switched_to_cash', 'gateway_checkout_url' => null])->save();
            $this->history($old->id, Payment::PENDING, Payment::FAILED, 'switched_to_cash');

            $attempt = (int) Payment::query()->where('order_id', $locked->id)->max('attempt_no') + 1;
            $new = new Payment([
                'order_id' => $locked->id,
                'method_code' => $method->code,
                'currency_code' => $old->currency_code,
                'attempt_no' => $attempt,
            ]);
            $new->forceFill([
                'amount' => $old->amount,
                'status' => Payment::AWAITING_COLLECTION,
                'idempotency_key' => 'order:'.$locked->id.':attempt:'.$attempt,
            ])->save();
            $this->history($new->id, null, Payment::AWAITING_COLLECTION, null);

            $locked->forceFill(['payment_method_code' => $method->code])->save();
            $this->payments->syncOrder($locked);
            $this->logger->log('payment.switched_to_cash', $locked, ['from' => $old->method_code, 'to' => $method->code]);

            return $new;
        });
    }

    /**
     * سفارش‌های آنلاینی که در وقتش پرداخت نشدند لغو می‌شوند (موجودی و کد تخفیف آزاد می‌شود).
     * پولی که بعداً برسد «نیاز به بررسی» می‌شود (PAID_ON_CLOSED).
     */
    public function cancelStale(): int
    {
        $minutes = max(30, (int) config('store.payments.online_cancel_after_minutes', 120));
        $orders = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('placed_at', '<', now()->subMinutes($minutes))
            ->whereIn('payment_method_code', $this->onlineCodes())
            ->where('paid_total', 0)
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::PENDING))
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $this->workflow->cancel($order, (string) __('payments.online.auto_cancel_reason', ['minutes' => $minutes]));
                $count++;
            } catch (Throwable $e) {
                Log::warning('payment.online_cancel_failed', ['order' => $order->number, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $count;
    }

    /** @return list<string> */
    private function onlineCodes(): array
    {
        return PaymentMethod::query()->where('type', 'gateway')->pluck('code')->all();
    }

    private function history(int $paymentId, ?string $from, string $to, ?string $note): void
    {
        OrderStatusHistory::query()->create([
            'subject_type' => 'payment',
            'subject_id' => $paymentId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => null,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
