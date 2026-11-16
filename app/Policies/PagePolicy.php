<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * دسترسی به صفحه‌های ثابت: صاحب، مدیر و ویرایشگر.
 */
class PagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Page $page): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Page $page): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, Page $page): bool
    {
        return $actor->role->canManageContent();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
