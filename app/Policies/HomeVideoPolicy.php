<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HomeVideo;
use App\Models\User;

/**
 * ویدیوهای صفحه‌ی اصلی: صاحب، مدیر و ویرایشگر (مثل صفحه‌های ثابت).
 */
class HomeVideoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, HomeVideo $video): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, HomeVideo $video): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, HomeVideo $video): bool
    {
        return $actor->role->canManageContent();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return $actor->role->canManageContent();
    }
}
