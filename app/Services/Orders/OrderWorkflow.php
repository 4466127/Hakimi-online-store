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

    /**
     * سفارش را **تا** وضعیت خواسته‌شده پیش می‌برد و پله‌های میانی را یکی‌یکی و با ثبت تاریخچه رد می‌کند.
     *
     * برای کار واقعی لازم است: پیک دمِ در «پول گرفتم و جنس را دادم» را می‌زند و سفارش باید به
     * «تحویل شد» برسد، حتی اگر کسی «ارسال شد» را نزده بود. هر پله جدا در تاریخچه می‌ماند،
     * پس سند کار دست‌نخورده است.
     *
     * @throws OrderTransitionException
     */
    public function advanceTo(Order $order, string $target, ?User $actor = null, ?string $note = null): Order
    {
        $ladder = [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];
        $targetIndex = array_search($target, $ladder, true);
        if ($targetIndex === false) {
            throw new OrderTransitionException('transition_denied', $order->status, $target);
        }

        for ($step = 0; $step < count($ladder); $step++) {
            $current = array_search($order->status, $ladder, true);
            if ($current === false || $current >= $targetIndex) {
                break;
            }
            $order = $this->advance($order, $ladder[$current + 1], $actor, $note);
        }

        if ($order->status !== $target) {
            throw new OrderTransitionException('transition_denied', $order->status, $target);
        }

        return $order;
    }

    /**
     * واگذاری سفارش به یک پیک (یا برداشتن واگذاری با null).
     * در حالت «تحویل یکپارچه» یک پیک همه‌ی سفارش را می‌برد، پس روی همه‌ی سفارش‌های فروشنده ثبت می‌شود.
     */
    public function assignCourier(Order $order, ?User $courier, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $courier, $actor): Order {
            foreach ($this->actionable($order) as $sellerOrder) {
                $sellerOrder->forceFill(['assigned_courier_id' => $courier?->id])->save();
            }

            $this->logger->log('order.courier_assigned', $order, [
                'courier_id' => $courier?->id,
                'courier' => $courier?->name,
            ], $actor);

            return $order->refresh();
        });
    }

    /** پیک واگذارشده‌ی این سفارش (در حالت یکپارچه یک نفر؛ اگر نبود null). */
    public function courier(Order $order): ?User
    {
        $id = SellerOrder::query()->where('order_id', $order->id)
            ->whereNotNull('assigned_courier_id')->value('assigned_courier_id');

        return $id === null ? null : User::query()->find($id);
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
