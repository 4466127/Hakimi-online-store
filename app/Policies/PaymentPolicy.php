<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * پرداخت‌ها: صاحب، مدیر و کارمند سفارش همه را می‌بینند؛ **پیک فقط پول‌هایی که خودش گرفته**.
 * پرداخت از پنل ساخته، ویرایش یا پاک نمی‌شود — سند مالی است و فقط سرویس‌های پرداخت آن را عوض می‌کنند.
 */
class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageOrders() || $actor->role->isCourier();
    }

    public function view(User $actor, Payment $record): bool
    {
        return $actor->role->canManageOrders()
            || ($actor->role->isCourier() && $record->collected_by === $actor->id);
    }

    /** تحویل گرفتن پول از پیک (تنها کاری که در این بخش ممکن است). */
    public function update(User $actor, Payment $record): bool
    {
        return $actor->role->canReceiveCourierCash();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, Payment $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, Payment $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }
}
