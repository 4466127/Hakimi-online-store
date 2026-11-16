<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * دسترسی به بخش «کاربران پنل»: فقط صاحب فروشگاه (owner).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->role->canManageUsers();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageUsers();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->role->canManageUsers();
    }

    /** کسی نمی‌تواند خودش را یا آخرین صاحب فروشگاه را حذف کند. */
    public function delete(User $actor, User $user): bool
    {
        if (! $actor->role->canManageUsers() || $actor->is($user)) {
            return false;
        }

        return ! self::isLastActiveOwner($user);
    }

    /** حذف گروهی کاربران نداریم (خطرناک است). */
    public function deleteAny(User $actor): bool
    {
        return false;
    }

    /** آیا این کاربر تنها صاحبِ فعال فروشگاه است؟ (نباید حذف، غیرفعال یا تنزل داده شود.) */
    public static function isLastActiveOwner(User $user): bool
    {
        if ($user->role !== UserRole::Owner || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
