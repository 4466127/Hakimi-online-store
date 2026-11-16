<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SellerCommissionRate;
use App\Models\User;

/**
 * نرخ ویژه‌ی فروشنده: صاحب و مدیر می‌سازند، تغییر می‌دهند و حذف می‌کنند.
 */
class SellerCommissionRatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, SellerCommissionRate $sellerCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, SellerCommissionRate $sellerCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, SellerCommissionRate $sellerCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
