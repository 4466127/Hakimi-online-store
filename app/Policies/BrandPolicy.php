<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/**
 * برندها: صاحب، مدیر و ویرایشگر اجناس.
 */
class BrandPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Brand $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Brand $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, Brand $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Brand $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function forceDelete(User $actor, Brand $record): bool
    {
        return false;
    }
}
