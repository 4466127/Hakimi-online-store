<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeliveryZone;
use App\Models\User;

/**
 * کرایه‌ی تحویل: صاحب و مدیر می‌بینند و عوض می‌کنند (مثل ولایت‌ها).
 * منطقه حذف نمی‌شود؛ بعد از ساخته شدن سفارش‌ها به آن وصل است. برای بستن، «فعال» را خاموش کنید.
 */
class DeliveryZonePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, DeliveryZone $record): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, DeliveryZone $record): bool
    {
        return $actor->role->canManageSettings();
    }

    public function delete(User $actor, DeliveryZone $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
