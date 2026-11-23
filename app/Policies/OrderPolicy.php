<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;

/**
 * سفارش‌ها: صاحب، مدیر و «کارمند سفارش» همه را می‌بینند.
 *
 * **پیک** (مرحله‌ی ۳) فقط سفارش‌هایی را می‌بیند که به خودش واگذار شده (`assigned_courier_id`)،
 * تا نه آدرس و تلفن مشتری‌های دیگر را ببیند و نه بتواند روی آنها کاری کند.
 *
 * سفارش هیچ‌وقت از پنل پاک نمی‌شود (سند مالی است؛ کار اشتباه با «لغو» و دلیل ثبت می‌شود).
 */
class OrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageOrders() || $actor->role->isCourier();
    }

    public function view(User $actor, Order $record): bool
    {
        return $actor->role->canManageOrders() || $this->isMine($actor, $record);
    }

    public function update(User $actor, Order $record): bool
    {
        return $actor->role->canManageOrders() || $this->isMine($actor, $record);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, Order $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Order $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    /** سفارشی که به همین پیک واگذار شده. */
    private function isMine(User $actor, Order $record): bool
    {
        return $actor->role->isCourier() && SellerOrder::query()
            ->where('order_id', $record->id)
            ->where('assigned_courier_id', $actor->id)
            ->exists();
    }
}
