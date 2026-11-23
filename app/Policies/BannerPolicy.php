<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

/**
 * بنرهای صفحه‌ی اصلی: صاحب، مدیر و ویرایشگر (مثل صفحه‌های ثابت).
 */
class BannerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function view(User $actor, Banner $banner): bool
    {
        return $actor->role->canManageContent();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageContent();
    }

    public function update(User $actor, Banner $banner): bool
    {
        return $actor->role->canManageContent();
    }

    public function delete(User $actor, Banner $banner): bool
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
