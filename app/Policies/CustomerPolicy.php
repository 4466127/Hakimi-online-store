<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * مشتریان: صاحب، مدیر و کارمند سفارش می‌بینند؛ فقط صاحب و مدیر تغییر می‌دهند.
 * مشتری از پنل ساخته یا حذف نمی‌شود (خودش ثبت‌نام می‌کند؛ به‌جای حذف، غیرفعال).
 */
class CustomerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageOrders();
    }

    public function view(User $actor, Customer $customer): bool
    {
        return $actor->role->canManageOrders();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Customer $customer): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, Customer $customer): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
