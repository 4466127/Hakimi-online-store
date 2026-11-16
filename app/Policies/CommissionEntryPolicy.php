<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CommissionEntry;
use App\Models\User;

/**
 * سند مالی: صاحب و مدیر فقط می‌بینند. ساختن فقط از راه CommissionService؛ ویرایش و حذف هرگز.
 */
class CommissionEntryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, CommissionEntry $commissionEntry): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, CommissionEntry $commissionEntry): bool
    {
        return false;
    }

    public function delete(User $actor, CommissionEntry $commissionEntry): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
