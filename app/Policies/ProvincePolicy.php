<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Province;
use App\Models\User;

/**
 * ولایت‌ها: صاحب و مدیر می‌توانند «تحویل فعال» را روشن و خاموش کنند.
 * ولایت ساخته یا حذف نمی‌شود (۳۴ ولایت ثابت است).
 */
class ProvincePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, Province $province): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, Province $province): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, Province $province): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
