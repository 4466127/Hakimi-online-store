<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک سطر تاریخچه‌ی فعالیت: چه کسی، چه وقت، چه کرد. فقط نوشته می‌شود؛ ویرایش و حذف ندارد.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip', 'created_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** کاربر پنل که این کار را کرد (فقط وقتی actor_type = user معنی دارد). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** مشتری که این کار را کرد (فقط وقتی actor_type = customer معنی دارد). */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'actor_id')->withTrashed();
    }

    /** نام کننده‌ی کار، یا null اگر کار سیستم بود. */
    public function actorName(): ?string
    {
        return match ($this->actor_type) {
            'user' => $this->user?->name,
            'customer' => $this->customer ? $this->customer->name.' ('.__('admin.activity.customer').')' : null,
            default => null,
        };
    }

    /** متن خوانای عمل: «کاربر ساخته شد». اگر ترجمه نبود همان کد. */
    public function actionLabel(): string
    {
        // نام عمل خودش نقطه دارد (user.created)، پس از کل لیست می‌خوانیم
        $labels = (array) __('admin.activity.actions');

        return (string) ($labels[$this->action] ?? $this->action);
    }
}
