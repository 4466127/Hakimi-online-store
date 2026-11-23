<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * کارهای مدیر روی یک سفارش (مرحله‌ی ۲-۳ برنامه‌ی کلان).
 *
 * تحویل در شروع «یکپارچه» است (ARCHITECTURE.md بخش ۷-۱۷): مدیر با یک کار همه‌ی «سفارش‌های فروشنده»ی
 * داخل سفارش را پیش می‌برد. خودِ تغییر وضعیت همیشه از `OrderStateMachine` می‌گذرد (قاعده‌ی پروژه:
 * هیچ‌جای دیگر `status` را دست نمی‌زند)، پس اثر روی موجودی، تاریخچه و لاگ همان‌جا انجام می‌شود.
 *
 * وضعیت سفارش والد از فرزندان حساب می‌شود؛ این کلاس آن را دست نمی‌زند.
 */
class OrderWorkflow
{
    public function __construct(
        private readonly OrderStateMachine $machine,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * وضعیت‌هایی که مدیر می‌تواند همین حالا برای این سفارش انتخاب کند
     * (هر وضعیتی که دست‌کم یکی از سفارش‌های فروشنده بتواند به آن برود).
     *
     * @return list<string>
     */
    public function available(Order $order): array
    {
        $next = [];
        foreach ($this->actionable($order) as $sellerOrder) {
            foreach ($this->machine->next($sellerOrder->status) as $to) {
                $next[$to] = true;
            }
        }

        return array_keys($next);
    }

    public function can(Order $order, string $to): bool
    {
        return in_array($to, $this->available($order), true);
    }

    /**
     * همه‌ی سفارش‌های فروشنده‌ی این سفارش را (آنهایی که می‌توانند) به وضعیت تازه می‌برد.
     *
     * @param  string|null  $note  دلیل؛ برای «لغو» لازم است
     *
     * @throws OrderTransitionException اگر هیچ سفارش فروشنده‌ای نتواند به این وضعیت برود
     */
    public function advance(Order $order, string $to, ?User $actor = null, ?string $note = null): Order
    {
        $moved = 0;
        foreach ($this->actionable($order) as $sellerOrder) {
            if ($this->machine->can($sellerOrder->status, $to)) {
                $this->machine->transition($sellerOrder, $to, $actor, $note);
                $moved++;
            }
        }

        if ($moved === 0) {
            throw new OrderTransitionException('transition_denied', $order->status, $to);
        }

        return $order->refresh();
    }

    /** لغو (رد) سفارش با دلیل. رزرو موجودی آزاد می‌شود و پرداخت منتظر «ناموفق» می‌شود. */
    public function cancel(Order $order, string $reason, ?User $actor = null): Order
    {
        return $this->advance($order, Order::STATUS_CANCELLED, $actor, $reason);
    }

    /** یادداشت مدیر (فقط برای کارمندان؛ مشتری آن را نمی‌بیند). */
    public function setAdminNote(Order $order, ?string $note, ?User $actor = null): Order
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);
        if ($note === $order->admin_note) {
            return $order;
        }

        DB::transaction(function () use ($order, $note, $actor): void {
            $order->forceFill(['admin_note' => $note])->save();
            $this->logger->log('order.note_updated', $order, ['note' => $note], $actor);
        });

        return $order;
    }

    /**
     * سفارش‌های فروشنده‌ای که هنوز کاری روی آنها ممکن است (لغو‌شده و برگشت‌خورده نه).
     *
     * @return Collection<int, SellerOrder>
     */
    private function actionable(Order $order)
    {
        return SellerOrder::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->orderBy('id')
            ->get();
    }
}
