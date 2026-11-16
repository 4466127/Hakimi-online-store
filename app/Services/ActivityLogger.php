<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * نوشتن در تاریخچه‌ی فعالیت.
 *
 * نوشتن تاریخچه هیچ‌وقت نباید کار اصلی را خراب کند؛ اگر خطا شد فقط در لاگ نوشته می‌شود.
 */
class ActivityLogger
{
    /**
     * @param  string  $action  مثل user.created ، settings.updated ، auth.login
     * @param  Model|null  $subject  چیزی که کار روی آن شد
     * @param  array<string, mixed>|null  $changes  قبل/بعد یا جزئیات
     * @param  User|Customer|null  $actor  اگر داده نشود: کاربر پنل واردشده، وگرنه مشتری واردشده
     */
    public function log(string $action, ?Model $subject = null, ?array $changes = null, User|Customer|null $actor = null): ?ActivityLog
    {
        try {
            $actor ??= $this->currentActor();

            return ActivityLog::create([
                'actor_type' => match (true) {
                    $actor instanceof User => 'user',
                    $actor instanceof Customer => 'customer',
                    default => 'system',
                },
                'actor_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $subject ? $this->subjectType($subject) : null,
                'subject_id' => $subject && is_int($subject->getKey()) ? $subject->getKey() : null,
                'subject_label' => $subject ? $this->subjectLabel($subject) : null,
                'changes' => $changes ?: null,
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function currentActor(): User|Customer|null
    {
        $user = auth()->guard('web')->user();
        if ($user instanceof User) {
            return $user;
        }
        $customer = auth()->guard('customer')->user();

        return $customer instanceof Customer ? $customer : null;
    }

    private function subjectType(Model $subject): string
    {
        return method_exists($subject, 'activitySubjectType')
            ? $subject->activitySubjectType()
            : strtolower(class_basename($subject));
    }

    private function subjectLabel(Model $subject): ?string
    {
        if (! method_exists($subject, 'activityLabel')) {
            return null;
        }

        return mb_substr((string) $subject->activityLabel(), 0, 150);
    }
}
