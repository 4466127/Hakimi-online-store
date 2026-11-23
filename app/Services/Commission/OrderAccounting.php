<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * پیوند سفارش و پول با حساب فروشندگان (مرحله‌ی ۴).
 *
 * قاعده‌ها (تصمیم‌های صاحب پروژه، ۱ و ۲ قوس ۱۴۰۵):
 * - طلب هر فروشنده **فقط** وقتی ثبت می‌شود که سفارشِ همان فروشنده «تحویل شد» **و** پول سفارش کامل رسیده باشد
 *   (`orders.payment_status = paid`). اگر کمتر گرفته شد (`partially_paid`)، هیچ طلبی ثبت نمی‌شود تا مدیر
 *   باقی را ثبت یا با دلیل تصمیم بگیرد — کمبود خودکار از سهم هیچ فروشنده‌ای کم نمی‌شود.
 * - سفارش فروشنده‌ای که لغو شد یا هنوز تحویل نشده، هیچ سندی ندارد (پول به فروشنده‌ی تحویل‌نداده تخصیص نمی‌یابد).
 * - مبلغ‌ها از **کپی قاعده در خط سفارش** خوانده می‌شود، نه از نرخ امروز.
 * - پولی که هنوز دست پیک است ← طلب «منتظر»؛ وقتی پیک پول را به صندوق داد ← «قابل تسویه» (+ روزهای انتظار).
 * - برگشت سفارش یک فروشنده بعد از تحویل ← سند برگشت با همان نرخ و ردیف منفی در دفتر؛ بقیه‌ی فروشنده‌ها دست نمی‌خورند.
 * - همه‌چیز بی‌تکرار (کلید یکتای هر خط) و داخل transaction؛ صدا زدن دوباره هیچ‌چیز را دو بار ثبت نمی‌کند.
 */
class OrderAccounting
{
    public function __construct(
        private readonly CommissionService $commission,
        private readonly SellerLedger $ledger,
    ) {}

    /**
     * سندهای فروش همه‌ی سفارش‌های فروشنده‌ی تحویل‌شده‌ی این سفارش (اگر پول کامل رسیده باشد).
     *
     * @return int تعداد خط‌هایی که تازه ثبت شد
     */
    public function postOrder(Order $order, ?User $actor = null): int
    {
        return DB::transaction(function () use ($order, $actor): int {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status !== 'paid') {
                return 0;
            }

            $payment = Payment::query()->where('order_id', $order->id)->where('status', Payment::PAID)
                ->orderByDesc('paid_at')->orderByDesc('id')->first();
            // پول دست پیک ← هنوز قابل تسویه نیست؛ پول نمایندگی/صندوق ← از همین حالا (+ روزهای انتظار)
            $eligibleAt = $payment !== null && $payment->courier_cash_status === Payment::HELD_BY_COURIER
                ? null
                : $this->ledger->eligibleFrom($payment?->collected_at ?? $payment?->paid_at);

            $posted = 0;
            $sellerOrders = SellerOrder::query()->where('order_id', $order->id)->where('status', 'delivered')->orderBy('id')->get();
            foreach ($sellerOrders as $sellerOrder) {
                $sellerOrder->setRelation('order', $order);
                $items = OrderItem::query()->where('seller_order_id', $sellerOrder->id)->whereNull('commission_entry_id')->orderBy('id')->get();
                foreach ($items as $item) {
                    $this->commission->recordOrderItem(
                        $item, $sellerOrder, $payment, $eligibleAt, $actor,
                        (string) __('commission.ledger.sale_description', ['order' => $order->number, 'product' => $item->product_name, 'qty' => $item->qty]),
                    );
                    $posted++;
                }
            }

            return $posted;
        });
    }

    /**
     * سفارش یک فروشنده بعد از تحویل برگشت خورد: طلب و کمیسیون همه‌ی خط‌هایش با همان نرخ برگشت می‌شود.
     *
     * @return int تعداد سندهای برگشت تازه
     */
    public function reverseSellerOrder(SellerOrder $sellerOrder, ?User $actor = null, ?string $note = null): int
    {
        return DB::transaction(function () use ($sellerOrder, $actor, $note): int {
            $count = 0;
            $items = OrderItem::query()->where('seller_order_id', $sellerOrder->id)->whereNotNull('commission_entry_id')->orderBy('id')->get();
            foreach ($items as $item) {
                $sale = CommissionEntry::query()->find($item->commission_entry_id);
                if ($sale === null || $sale->kind !== CommissionEntry::KIND_SALE) {
                    continue;
                }
                $left = bcadd((string) $sale->gross_amount, (string) CommissionEntry::query()->where('reverses_id', $sale->id)->sum('gross_amount'), 2);
                if (bccomp($left, '0', 2) <= 0) {
                    continue;                                             // قبلاً کامل برگشت شده
                }
                $this->commission->recordReversal(
                    $sale, 'seller_order_return:'.$item->id, null,
                    (string) __('commission.ledger.return_description', ['order' => $sellerOrder->number, 'product' => $item->product_name])
                        .($note ? ' — '.$note : ''),
                    $actor,
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * پیک پول این پرداخت‌ها را به صندوق داد: طلب‌های منتظر فروشندگان قابل تسویه می‌شوند.
     *
     * @param  list<int>  $paymentIds
     */
    public function releaseCourierCash(array $paymentIds): int
    {
        return $this->ledger->releaseForPayments($paymentIds);
    }
}
