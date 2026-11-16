<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * ساختن، تغییر و حذف مدل را در تاریخچه‌ی فعالیت می‌نویسد (چه کسی، چه چیز، قبل و بعد).
 *
 * مدل می‌تواند تعریف کند:
 *   protected array $activityHidden = ['password'];  // ستون‌هایی که نوشته نشود
 *   public function activityLabel(): string           // نام خوانا در تاریخچه
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            app(ActivityLogger::class)->log($model->activityAction('created'), $model);
        });

        static::updated(function (Model $model): void {
            $changes = $model->activityChanges();
            if ($changes !== []) {
                app(ActivityLogger::class)->log($model->activityAction('updated'), $model, $changes);
            }
        });

        static::deleted(function (Model $model): void {
            app(ActivityLogger::class)->log($model->activityAction('deleted'), $model);
        });
    }

    /** نام عمل: user.created ، page.updated ، … */
    public function activityAction(string $event): string
    {
        return $this->activitySubjectType().'.'.$event;
    }

    public function activitySubjectType(): string
    {
        return strtolower(class_basename($this));
    }

    /** @return array<string, array{old: mixed, new: mixed}> */
    public function activityChanges(): array
    {
        $hidden = array_merge(
            ['updated_at', 'created_at'],
            property_exists($this, 'activityHidden') ? $this->activityHidden : [],
        );
        $out = [];
        foreach ($this->getChanges() as $key => $new) {
            if (in_array($key, $hidden, true)) {
                continue;
            }
            $out[$key] = ['old' => $this->getOriginal($key), 'new' => $this->getAttribute($key)];
        }

        return $out;
    }
}
