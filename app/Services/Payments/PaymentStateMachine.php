<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * تنها راه عوض کردن وضعیت پرداخت (مرحله‌ی ۳ برنامه‌ی کلان).
 *
 * قاعده‌ی بنیادی پروژه: **هیچ‌چیز «پرداخت‌شده» نمی‌شود مگر با شاهد قابل اعتماد** — پول نقدِ
 * تأییدشده‌ی شخص مجاز (با کد تحویل)، یا جواب سرور-به-سرور و تأییدشده‌ی درگاه. صفحه‌ی بازگشت
 * مرورگر، ثبت سفارش، تأیید و ارسال، هیچ‌کدام شاهد پرداخت نیستند.
 *
 * گذارها:
 *   awaiting_collection → paid | failed      pending → paid | failed
 *   paid → partially_refunded | refunded (مرحله‌ی ۷)   partially_refunded → refunded
 *   failed و refunded پایان‌اند (تلاش تازه = ردیف پرداخت تازه با `attempt_no` و کلید بی‌تکرار تازه).
 *
 * هر تغییر داخل transaction با قفل ردیف سفارش و پرداخت است، در `order_status_history`
 * (subject_type = payment) و `activity_log` ثبت می‌شود، و بعد از آن جمع پول‌رسیده و
 * «وضعیت پرداخت» سفارش از روی خود پرداخت‌ها دوباره حساب می‌شود.
 */
class PaymentStateMachine
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        Payment::AWAITING_COLLECTION => [Payment::PAID, Payment::FAILED],
        Payment::PENDING => [Payment::PAID, Payment::FAILED],
        Payment::PAID => [Payment::PARTIALLY_REFUNDED, Payment::REFUNDED],
        Payment::PARTIALLY_REFUNDED => [Payment::REFUNDED],
        Payment::FAILED => [],
        Payment::REFUNDED => [],
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * «پول رسید» را ثبت می‌کند و رسید می‌سازد.
     *
     * @param  array<string, mixed>  $evidence  ستون‌های شاهد (گیرنده‌ی پول، محل، کد تحویل بررسی‌شده، مرجع درگاه …)
     *
     * @throws PaymentException
     */
    public function markPaid(
        Payment $payment,
        string|int|float $amountReceived,
        array $evidence = [],
        ?User $actor = null,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($payment, $amountReceived, $evidence, $actor, $note): Payment {
            // ترتیب ثابت قفل‌ها (اول سفارش، بعد پرداخت) جلوی deadlock را می‌گیرد
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $from = $row->status;
            if (! $this->can($from, Payment::PAID)) {
                throw new PaymentException($from === Payment::PAID ? 'already_paid' : 'not_collectable');
            }

            $amount = Money::of($amountReceived);
            if (Money::compare($amount, 0) <= 0) {
                throw new PaymentException('amount_invalid');
            }

            $row->forceFill($evidence + [
                'status' => Payment::PAID,
                'amount_received' => $amount,
                'paid_at' => now(),
                'receipt_number' => $row->receipt_number ?? Payment::receiptFor($row->id),
                'failed_reason' => null,
            ]);
            if ($note !== null && trim($note) !== '') {
                $row->forceFill(['note' => trim($note)]);
            }
            $row->save();

            $this->history($row->id, $from, Payment::PAID, $actor, $note);
            $this->logger->log('payment.paid', $row, [
                'order' => $order->number,
                'amount' => $amount,
                'receipt' => $row->receipt_number,
                'place' => $row->collected_at_type,
            ], $actor);

            $this->syncOrder($order, $actor);

            return $row->refresh();
        });
    }

    /**
     * پرداخت ناموفق (مشتری نبود، نپذیرفت، منقضی شد، درگاه رد کرد …). هیچ پولی ثبت نمی‌شود.
     *
     * @throws PaymentException
     */
    public function fail(Payment $payment, string $reason, ?User $actor = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor, $note): Payment {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $from = $row->status;
            if (! $this->can($from, Payment::FAILED)) {
                throw new PaymentException($from === Payment::PAID ? 'already_paid' : 'not_collectable');
            }

            $row->forceFill(['status' => Payment::FAILED, 'failed_reason' => mb_substr($reason, 0, 30)])->save();

            $this->history($row->id, $from, Payment::FAILED, $actor, $note ?? $reason);
            $this->logger->log('payment.failed', $row, ['order' => $order->number, 'reason' => $reason], $actor);

            $this->syncOrder($order, $actor);

            return $row->refresh();
        });
    }

    /**
     * جمع پول‌رسیده و «وضعیت پرداخت» سفارش را از روی خود ردیف‌های پرداخت دوباره حساب می‌کند
     * (هیچ‌جای دیگر `paid_total` و `payment_status` را دست نمی‌زند).
     */
    public function syncOrder(Order $order, ?User $actor = null): Order
    {
        $payments = Payment::query()->where('order_id', $order->id)->get();

        $paid = $payments->where('status', Payment::PAID)
            ->reduce(fn (string $carry, Payment $p): string => Money::add($carry, $p->amount_received ?? $p->amount), '0.00');
        $refunded = Money::of($order->refunded_total);

        $status = match (true) {
            Money::compare($paid, 0) > 0 && Money::compare($paid, $order->total) >= 0 => 'paid',
            Money::compare($paid, 0) > 0 => 'partially_paid',
            $payments->contains(fn (Payment $p): bool => $p->status === Payment::PENDING) => 'pending',
            $payments->contains(fn (Payment $p): bool => $p->status === Payment::AWAITING_COLLECTION) => 'awaiting_collection',
            $payments->contains(fn (Payment $p): bool => $p->status === Payment::FAILED) => 'failed',
            default => 'unpaid',
        };
        if (Money::compare($refunded, 0) > 0) {
            $status = Money::compare($refunded, $paid) >= 0 ? 'refunded' : 'partially_refunded';
        }

        $was = $order->payment_status;
        $order->forceFill(['paid_total' => $paid, 'payment_status' => $status])->save();

        if ($was !== $status) {
            $this->logger->log('order.payment_status_changed', $order, [
                'from' => $was,
                'to' => $status,
                'paid_total' => $paid,
            ], $actor);
        }

        return $order;
    }

    private function history(int $paymentId, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        OrderStatusHistory::query()->create([
            'subject_type' => 'payment',
            'subject_id' => $paymentId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->id,
            'note' => $note === null || trim($note) === '' ? null : trim($note),
            'created_at' => now(),
        ]);
    }
}
