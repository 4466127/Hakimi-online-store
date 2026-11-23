<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCommissionRate;
use App\Models\User;

/**
 * قاعده‌ی کمیسیون یک جنس: صاحب و مدیر می‌سازند، تغییر می‌دهند و حذف می‌کنند.
 */
class ProductCommissionRatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, ProductCommissionRate $productCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, ProductCommissionRate $productCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, ProductCommissionRate $productCommissionRate): bool
    {
        return $actor->role->canManageSettings();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
