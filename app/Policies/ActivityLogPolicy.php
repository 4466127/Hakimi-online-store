<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * تاریخچه‌ی فعالیت فقط دیده می‌شود (صاحب و مدیر)؛ هیچ‌کس آن را عوض یا پاک نمی‌کند.
 */
class ActivityLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canViewActivity();
    }

    public function view(User $actor, ActivityLog $log): bool
    {
        return $actor->role->canViewActivity();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $actor, ActivityLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
