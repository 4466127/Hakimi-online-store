<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiscountCode;
use App\Models\User;

/**
 * کدهای تخفیف (مرحله‌ی ۹-۳): فقط صاحب و مدیر (پول بازارک است).
 * کدی که یک بار استفاده شده حذف نمی‌شود (تاریخچه)؛ خاموش می‌شود.
 */
class DiscountCodePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, DiscountCode $code): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, DiscountCode $code): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, DiscountCode $code): bool
    {
        return $actor->role->canManageSettings() && ! $code->redemptions()->exists();
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
