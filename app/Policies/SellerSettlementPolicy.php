<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SellerSettlement;
use App\Models\User;

/**
 * سند مالی: صاحب و مدیر فقط می‌بینند. ساختن فقط از راه CommissionService؛ ویرایش و حذف هرگز.
 */
class SellerSettlementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, SellerSettlement $sellerSettlement): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, SellerSettlement $sellerSettlement): bool
    {
        return false;
    }

    public function delete(User $actor, SellerSettlement $sellerSettlement): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
