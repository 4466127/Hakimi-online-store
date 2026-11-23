<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تنها راه عوض کردن وضعیت سفارش (قاعده‌ی پروژه: هیچ جای دیگر `status` را دست نمی‌زند).
 *
 * وضعیت را **سفارش هر فروشنده** دارد؛ وضعیت سفارش والد از روی آنها حساب می‌شود (rollUp):
 *   - همه لغو ← لغو؛ همه برگشت ← برگشت؛
 *   - وگرنه «عقب‌مانده‌ترین» سفارش فروشنده‌ی غیرلغو (تا همه تحویل نشده، والد «تحویل شد» نیست).
 *
 * گذارها:
 *   pending → confirmed | cancelled          confirmed → processing | cancelled
 *   processing → shipped | cancelled         shipped → delivered | cancelled (تحویل‌نشد/نپذیرفت)
 *   delivered → returned                      cancelled و returned پایان‌اند.
 *
 * اثر روی موجودی: لغو ← رزرو آزاد؛ تحویل ← موجودی کم و رزرو آزاد. هر گذار داخل یک transaction با قفل
 * ردیف سفارش والد، و در `order_status_history` + `activity_log` ثبت می‌شود.
 * ثبت کمیسیون (تحویل + پول رسیده) و بازپرداخت در مرحله‌های ۴ و ۷ از همین نقطه‌ها وصل می‌شود.
 */
class OrderStateMachine
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    /** ترتیب پیشرفت برای حساب وضعیت والد. */
    private const RANK = ['pending' => 0, 'confirmed' => 1, 'processing' => 2, 'shipped' => 3, 'delivered' => 4];

    public function __construct(
        private readonly StockLedger $stock,
        private readonly ActivityLogger $logger,
    ) {}

    public function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public function next(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * سفارش یک فروشنده را به وضعیت تازه می‌برد.
     *
     * @param  string|null  $note  دلیل؛ برای «لغو» لازم است
     *
     * @throws OrderTransitionException
     */
    public function transition(SellerOrder $sellerOrder, string $to, ?User $actor = null, ?string $note = null): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder, $to, $actor, $note): SellerOrder {
            // قفل: اول والد، بعد سفارش فروشنده (ترتیب ثابت، جلوی deadlock)
            $order = Order::query()->whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $so = SellerOrder::query()->whereKey($sellerOrder->id)->lockForUpdate()->firstOrFail();

            $from = $so->status;
            if (! $this->can($from, $to)) {
                throw new OrderTransitionException('transition_denied', $from, $to);
            }
            $note = $note === null ? null : trim($note);
            if ($to === 'cancelled' && ($note === null || $note === '')) {
                throw new OrderTransitionException('reason_required', $from, $to);
            }

            $now = now();
            $so->forceFill(['status' => $to] + match ($to) {
                'shipped' => ['shipped_at' => $now],
                'delivered' => ['delivered_at' => $now],
                'cancelled' => ['cancelled_at' => $now, 'cancel_reason' => $note],
                default => [],
            })->save();

            if ($to === 'cancelled') {
                $this->cancelItems($so, $actor, $note);
            } elseif ($to === 'delivered') {
                $this->sellItems($so, $actor);
            }

            $this->history('seller_order', $so->id, $from, $to, $actor, $note);
            $this->logger->log('seller_order.status_changed', $so, ['from' => $from, 'to' => $to, 'note' => $note], $actor);

            $this->rollUp($order, $actor, $note);

            return $so->refresh();
        });
    }

    // ───────────────────────── اثر روی موجودی ─────────────────────────

    private function cancelItems(SellerOrder $so, ?User $actor, ?string $note): void
    {
        foreach ($this->itemsByVariant($so) as $item) {
            $open = $item->activeQty();
            if ($item->variant_id !== null && $open > 0) {
                $this->stock->release($item->variant_id, $open, 'seller_order', $so->id, $actor, $note);
            }
            $item->forceFill(['qty_cancelled' => $item->qty - $item->qty_returned])->save();
        }
    }

    private function sellItems(SellerOrder $so, ?User $actor): void
    {
        foreach ($this->itemsByVariant($so) as $item) {
            $open = $item->activeQty();
            if ($item->variant_id !== null && $open > 0) {
                $this->stock->sell($item->variant_id, $open, 'seller_order', $so->id, $actor);
            }
        }
    }

    /**
     * خط‌های سفارش فروشنده به ترتیب نوع (همیشه همین ترتیب؛ قفل‌ها جور می‌شوند و deadlock نمی‌شود).
     *
     * @return Collection<int, OrderItem>
     */
    private function itemsByVariant(SellerOrder $so)
    {
        return $so->items()->orderBy('variant_id')->orderBy('id')->get();
    }

    // ───────────────────────── وضعیت والد ─────────────────────────

    private function rollUp(Order $order, ?User $actor, ?string $note): void
    {
        $sellerOrders = SellerOrder::query()->where('order_id', $order->id)->get();
        $active = $sellerOrders->where('status', '!=', 'cancelled');

        $new = match (true) {
            $active->isEmpty() => 'cancelled',
            $active->every(fn (SellerOrder $s) => $s->status === 'returned') => 'returned',
            default => (string) array_search(
                $active->map(fn (SellerOrder $s) => self::RANK[$s->status] ?? self::RANK['delivered'])->min(),
                self::RANK,
                true,
            ),
        };

        $this->recalculateTotals($order, $active);

        $old = $order->status;
        if ($new !== $old) {
            $now = now();
            $order->forceFill(['status' => $new] + match ($new) {
                'confirmed' => ['confirmed_at' => $order->confirmed_at ?? $now],
                'shipped' => ['shipped_at' => $order->shipped_at ?? $now],
                'delivered' => ['delivered_at' => $order->delivered_at ?? $now],
                'cancelled' => ['cancelled_at' => $now, 'cancel_reason' => $note, 'cancelled_by' => $actor?->id],
                default => [],
            });
            if ($new === 'cancelled') {
                $this->failOpenPayments($order);
            }
        }

        $order->save();           // جمع‌های تازه و/یا وضعیت تازه (اگر تغییری نبود، save کاری نمی‌کند)

        if ($new !== $old) {
            $this->history('order', $order->id, $old, $new, $actor, $note);
            $this->logger->log('order.status_changed', $order, ['from' => $old, 'to' => $new], $actor);
        }
    }

    /**
     * سفارش فروشنده‌ای لغو شد: جمع سفارش والد و مبلغ پرداخت منتظر (هنوز پولی نگرفته‌ایم) تازه می‌شود.
     * اگر پولی گرفته شده، جمع‌ها دست نمی‌خورد؛ برگشت پول با فرایند بازپرداخت است (مرحله‌ی ۷).
     * کرایه‌ی تحویل عوض نمی‌شود (تحویل یکپارچه)؛ اگر لازم بود مدیر دستی تنظیم می‌کند.
     *
     * @param  Collection<int, SellerOrder>  $active
     */
    private function recalculateTotals(Order $order, $active): void
    {
        if ($active->isEmpty() || Money::compare($order->paid_total, 0) > 0) {
            return;
        }

        $gross = $active->reduce(fn (string $c, SellerOrder $s) => Money::add($c, $s->gross_subtotal), '0.00');
        $discount = $active->reduce(
            fn (string $c, SellerOrder $s) => Money::add($c, Money::add($s->seller_discount_total, $s->platform_discount_total)),
            '0.00',
        );
        $total = Money::add(Money::sub($gross, $discount), Money::add($order->delivery_fee, $order->payment_fee));

        if (Money::compare($order->total, $total) === 0 && Money::compare($order->subtotal, $gross) === 0) {
            return;
        }

        $order->forceFill(['subtotal' => $gross, 'discount_total' => $discount, 'total' => $total]);
        Payment::query()
            ->where('order_id', $order->id)
            ->where('status', Payment::AWAITING_COLLECTION)
            ->update(['amount' => $total]);
    }

    /** سفارش کامل لغو شد: پرداخت‌های منتظر، ناموفق (دلیل: لغو) می‌شوند. هیچ پولی ثبت نمی‌شود. */
    private function failOpenPayments(Order $order): void
    {
        $payments = Payment::query()->where('order_id', $order->id)
            ->whereIn('status', [Payment::AWAITING_COLLECTION, Payment::PENDING])->get();

        foreach ($payments as $payment) {
            $from = $payment->status;
            $payment->forceFill(['status' => Payment::FAILED, 'failed_reason' => 'cancelled'])->save();
            $this->history('payment', $payment->id, $from, Payment::FAILED, null, 'cancelled');
        }
        if ($payments->isNotEmpty() && in_array($order->payment_status, ['unpaid', 'awaiting_collection', 'pending'], true)) {
            $order->forceFill(['payment_status' => 'failed']);
        }
    }

    private function history(string $type, int $id, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        OrderStatusHistory::query()->create([
            'subject_type' => $type,
            'subject_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
