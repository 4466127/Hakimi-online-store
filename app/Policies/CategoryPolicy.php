<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * دسته‌ها: صاحب، مدیر و ویرایشگر اجناس.
 */
class CategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Category $category): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Category $category): bool
    {
        return $actor->role->canManageContent();
    }

    /** دسته‌ای که زیردسته دارد حذف نمی‌شود. */
    public function delete(User $actor, Category $category): bool
    {
        return $actor->role->canManageContent() && ! $category->children()->exists();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Category $category): bool
    {
        return $actor->role->canManageContent();
    }

    public function forceDelete(User $actor, Category $category): bool
    {
        return false;
    }
}
