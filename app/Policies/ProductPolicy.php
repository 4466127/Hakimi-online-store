<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * اجناس: صاحب، مدیر و ویرایشگر اجناس. حذف همیشه «نرم» است (به سطل زباله، برگشت‌پذیر).
 */
class ProductPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Product $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Product $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, Product $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function restore(User $actor, Product $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function restoreAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function forceDelete(User $actor, Product $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }
}
