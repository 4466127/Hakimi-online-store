<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

/**
 * فروشندگان: صاحب و مدیر می‌بینند، تأیید/رد می‌کنند و یادداشت می‌نویسند.
 * فروشنده از پنل ساخته یا حذف نمی‌شود (خودش از سایت ثبت‌نام می‌کند).
 */
class SellerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, Seller $seller): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Seller $seller): bool
    {
        return $actor->role->canManageSettings();
    }

    public function review(User $actor, Seller $seller): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, Seller $seller): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
