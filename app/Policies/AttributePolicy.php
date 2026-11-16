<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

/**
 * مشخصه‌های کالا: صاحب، مدیر و ویرایشگر اجناس.
 */
class AttributePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Attribute $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Attribute $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, Attribute $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Attribute $record): bool
    {
        return $actor->role->canManageContent();
    }

    public function forceDelete(User $actor, Attribute $record): bool
    {
        return false;
    }
}
