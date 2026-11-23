<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * سفارش‌ها: صاحب، مدیر و «کارمند سفارش». سفارش هیچ‌وقت از پنل پاک نمی‌شود
 * (سند مالی است؛ کار اشتباه با «لغو» و دلیل ثبت می‌شود).
 */
class OrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageOrders();
    }

    public function view(User $actor, Order $record): bool
    {
        return $actor->role->canManageOrders();
    }

    public function update(User $actor, Order $record): bool
    {
        return $actor->role->canManageOrders();
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
}
