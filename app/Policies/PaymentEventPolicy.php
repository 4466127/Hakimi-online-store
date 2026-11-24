<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentEvent;
use App\Models\User;

/**
 * پیام‌های درگاه پرداخت (حساب‌پی): فقط صاحب و مدیر. دفتر است: ساختن، ویرایش و حذف ندارد؛
 * فقط «بررسی شد» (update) برای پیام‌هایی که نیاز به بررسی دارند.
 */
class PaymentEventPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->canManageSettings();
    }

    public function view(User $actor, PaymentEvent $record): bool
    {
        return $actor->role->canManageSettings();
    }

    public function update(User $actor, PaymentEvent $record): bool
    {
        return $actor->role->canManageSettings();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, PaymentEvent $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
