<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\CollectionPoint;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderWorkflow;
use App\Support\Money;
use App\Support\Num;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * گرفتن پول نقد (مرحله‌ی ۳ برنامه‌ی کلان): دمِ در توسط پیک و در نمایندگی، **با بررسی کد تحویل**.
 *
 * قاعده‌های کار (docs/PAYMENTS_AND_CHECKOUT.md بخش ۳):
 *  - **کد تحویل:** مشتری کد پنج‌رقمی صفحه‌ی سفارشش را می‌گوید. کد غلط ← پول ثبت نمی‌شود و
 *    جنس تحویل داده نمی‌شود (تلاش غلط در تاریخچه‌ی فعالیت ثبت می‌شود).
 *  - **اجازه:** صاحب، مدیر و کارمند سفارش هر جا؛ **پیک فقط سفارش واگذارشده‌ی خودش و فقط دمِ در**.
 *  - **مبلغ:** اگر با مبلغ سفارش یکی نبود، دلیل لازم است (کم‌آوردن یا بیشتر دادن بی دلیل ثبت نمی‌شود).
 *  - **رسید:** هر دریافت یک شماره‌ی یکتا (`RC-…`) می‌گیرد.
 *  - **پول دست پیک:** پول گرفته‌شده‌ی دمِ در تا تحویل به صندوق «دست پیک» است و تسویه‌اش ثبت جدا دارد.
 *  - همه‌چیز داخل یک transaction با قفل ردیف سفارش و پرداخت؛ وضعیت پرداخت فقط از
 *    `PaymentStateMachine` و وضعیت سفارش فقط از `OrderStateMachine` می‌گذرد.
 */
class CashCollectionService
{
    public function __construct(
        private readonly PaymentStateMachine $payments,
        private readonly OrderWorkflow $orders,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * «پول گرفته شد» را ثبت می‌کند (و اگر خواسته شد، سفارش را «تحویل شد» می‌کند).
     *
     * @throws PaymentException
     */
    public function collect(Order $order, CashCollection $data, User $actor): Payment
    {
        $place = $order->isPickup() ? Payment::AT_POINT : Payment::AT_DOOR;

        $this->checkActor($order, $place, $actor);
        $this->checkOrder($order);

        $payment = $this->openPayment($order);
        $this->checkDeliveryCode($order, $data->deliveryCode, $actor);
        $amount = $this->checkAmount($order, $data);
        $pointId = $place === Payment::AT_POINT ? $this->pointId($order, $data) : null;

        return DB::transaction(function () use ($order, $data, $actor, $payment, $amount, $place, $pointId): Payment {
            $paid = $this->payments->markPaid($payment, $amount, [
                'collected_by' => $actor->id,
                'collected_at_type' => $place,
                'collection_point_id' => $pointId,
                'collected_at' => now(),
                // پول دمِ در تا رسیدن به صندوق «دست پیک» است؛ پول نمایندگی همان‌جا در صندوق است
                'courier_cash_status' => $place === Payment::AT_DOOR ? Payment::HELD_BY_COURIER : null,
                'payer_name' => $data->payerName === null || trim($data->payerName) === '' ? null : trim($data->payerName),
                'delivery_code_verified' => true,
            ], $actor, $data->note);

            if ($data->markDelivered) {
                $this->orders->advanceTo($order->refresh(), Order::STATUS_DELIVERED, $actor);
            }

            return $paid;
        });
    }

    /**
     * پول گرفته‌شده‌ی پیک تحویل صندوق (دفتر یا نمایندگی) شد.
     *
     * @param  Collection<int, Payment>|iterable<Payment>  $payments
     * @return int تعداد ردیف‌هایی که تسویه شد
     *
     * @throws PaymentException
     */
    public function handOver(iterable $payments, User $receiver, ?string $note = null): int
    {
        $rows = collect($payments)->filter(
            fn (Payment $payment): bool => $payment->isPaid() && $payment->courier_cash_status === Payment::HELD_BY_COURIER,
        );

        if ($rows->isEmpty()) {
            throw new PaymentException('nothing_to_hand_over');
        }

        return DB::transaction(function () use ($rows, $receiver): int {
            $done = 0;
            foreach ($rows as $payment) {
                /** @var Payment $row */
                $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($row->courier_cash_status !== Payment::HELD_BY_COURIER) {
                    continue;                       // کسی دیگر همین حالا تسویه کرده
                }
                $row->forceFill([
                    'courier_cash_status' => Payment::HANDED_OVER,
                    'handed_over_to' => $receiver->id,
                    'handed_over_at' => now(),
                ])->save();

                $this->logger->log('payment.cash_handed_over', $row, [
                    'receipt' => $row->receipt_number,
                    'amount' => $row->amount_received,
                    'courier_id' => $row->collected_by,
                ], $receiver);
                $done++;
            }

            if ($done === 0) {
                throw new PaymentException('nothing_to_hand_over');
            }

            return $done;
        });
    }

    /**
     * گزارش «پول در دست پیک‌ها»: هر پیک، چند رسید و چند افغانی.
     *
     * @return Collection<int, array{courier: ?User, count: int, total: string}>
     */
    public function heldByCourier(): Collection
    {
        return Payment::query()->heldByCourier()->with('collector')->get()
            ->groupBy('collected_by')
            ->map(fn (Collection $rows): array => [
                'courier' => $rows->first()->collector,
                'count' => $rows->count(),
                'total' => $rows->reduce(fn (string $carry, Payment $p): string => Money::add($carry, $p->amount_received), '0.00'),
            ])
            ->values();
    }

    /** همین کاربر می‌تواند پول این سفارش را بگیرد؟ (برای نشان دادن یا ندادن دکمه در پنل) */
    public function canCollect(Order $order, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        try {
            $this->checkActor($order, $order->isPickup() ? Payment::AT_POINT : Payment::AT_DOOR, $actor);
            $this->checkOrder($order);
            $this->openPayment($order);
        } catch (PaymentException $e) {
            return false;
        }

        return true;
    }

    /** پیک‌های این سفارش (در حالت تحویل یکپارچه یک نفر). */
    public function courierIds(Order $order): array
    {
        return SellerOrder::query()->where('order_id', $order->id)
            ->whereNotNull('assigned_courier_id')
            ->pluck('assigned_courier_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    // ───────────────────────── بررسی‌ها ─────────────────────────

    /** @throws PaymentException */
    private function checkActor(Order $order, string $place, User $actor): void
    {
        if (! $actor->role->canCollectCash()) {
            throw new PaymentException('not_allowed');
        }
        if (! $actor->role->isCourier()) {
            return;
        }
        if ($place !== Payment::AT_DOOR) {
            throw new PaymentException('courier_door_only');
        }
        if (! in_array($actor->id, $this->courierIds($order), true)) {
            throw new PaymentException('not_your_order');
        }
    }

    /** @throws PaymentException */
    private function checkOrder(Order $order): void
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_RETURNED], true)) {
            throw new PaymentException('order_closed');
        }

        $method = PaymentMethod::query()->where('code', $order->payment_method_code)->first();
        if ($method !== null && ! $method->isCash()) {
            throw new PaymentException('not_cash');
        }
    }

    /**
     * پرداخت منتظرِ این سفارش (آخرین تلاش).
     *
     * @throws PaymentException
     */
    private function openPayment(Order $order): Payment
    {
        $payment = Payment::query()->where('order_id', $order->id)
            ->whereIn('status', [Payment::AWAITING_COLLECTION, Payment::PENDING])
            ->orderByDesc('id')->first();

        if ($payment !== null) {
            return $payment;
        }

        $paid = Payment::query()->where('order_id', $order->id)->where('status', Payment::PAID)->exists();

        throw new PaymentException($paid ? 'already_paid' : 'no_payment');
    }

    /**
     * کد تحویل مشتری. غلط بودنش در تاریخچه‌ی فعالیت ثبت می‌شود (سند تلاش نادرست).
     *
     * @throws PaymentException
     */
    private function checkDeliveryCode(Order $order, ?string $given, User $actor): void
    {
        $code = Num::parse($given === null ? null : trim($given));
        if ($code === null || $code === '') {
            throw new PaymentException('delivery_code_required');
        }

        if (ltrim($code, '0') !== ltrim((string) $order->delivery_code, '0')) {
            $this->logger->log('payment.delivery_code_wrong', $order, ['order' => $order->number], $actor);

            throw new PaymentException('delivery_code_wrong');
        }
    }

    /**
     * مبلغ گرفته‌شده. اختلاف با مبلغ سفارش دلیل می‌خواهد.
     *
     * @throws PaymentException
     */
    private function checkAmount(Order $order, CashCollection $data): string
    {
        $amount = Money::of(Num::parse(is_string($data->amountReceived) ? $data->amountReceived : (string) $data->amountReceived));
        if (Money::compare($amount, 0) <= 0) {
            throw new PaymentException('amount_invalid');
        }

        $expected = Money::sub($order->total, $order->paid_total);
        if (Money::compare($amount, $expected) !== 0 && ($data->note === null || trim($data->note) === '')) {
            throw new PaymentException('reason_required');
        }

        return $amount;
    }

    /** @throws PaymentException */
    private function pointId(Order $order, CashCollection $data): int
    {
        $id = $data->collectionPointId
            ?? $order->collection_point_id
            ?? CollectionPoint::query()->where('is_active', true)->orderBy('position')->value('id');

        if ($id === null) {
            throw new PaymentException('point_required');
        }

        return (int) $id;
    }
}
