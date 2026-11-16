<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * اعلان‌های پنل مدیریت (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵):
 *   - موفقیت: سبز، ۸ ثانیه.
 *   - خطا: سرخ، با دلیل، و می‌ماند تا مدیر ببندد (تا دلیل را بخواند).
 * همه‌ی پنل از همین‌جا می‌گذرد تا شکل و زمان یکسان باشد.
 */
final class AdminNotify
{
    /** زمان نمایش اعلان موفقیت (میلی‌ثانیه). */
    public const SUCCESS_MS = 8000;

    /** بیشترین تعداد دلیل در یک اعلان خطا. */
    private const MAX_REASONS = 5;

    public static function done(string $title, ?string $body = null): void
    {
        $notification = Notification::make()->success()->title($title)->duration(self::SUCCESS_MS);
        if ($body !== null && $body !== '') {
            $notification->body($body);
        }
        $notification->send();
    }

    /** خطای سرخ با دلیل (متن ساده، یا چند خط با <br>؛ Filament آن را پاک‌سازی می‌کند)؛ می‌ماند تا بسته شود. */
    public static function failed(string $title, ?string $reason = null): void
    {
        $notification = Notification::make()->danger()->title($title)->persistent();
        if ($reason !== null && $reason !== '') {
            $notification->body($reason);
        }
        $notification->send();
    }

    /**
     * اعلان سرخ یکسان در همان درخواست دوباره فرستاده نشود (مثلاً هم از خود سرویس و هم از قاعده‌ی عمومی فورم).
     */
    private static function alreadySent(string $fingerprint): bool
    {
        $request = request();
        if ($request->attributes->get('admin_notify_last') === $fingerprint) {
            return true;
        }
        $request->attributes->set('admin_notify_last', $fingerprint);

        return false;
    }

    /** «ذخیره نشد» + دلیل‌ها (بیشتر از پنج تا: «و n مورد دیگر»). */
    public static function validationFailed(ValidationException $exception): void
    {
        $messages = collect($exception->errors())->flatten()
            ->map(fn ($message): string => trim((string) $message))
            ->filter()->unique()->values();

        if ($messages->isEmpty()) {
            return;
        }

        if (self::alreadySent($messages->implode('|'))) {
            return;
        }

        $shown = $messages->take(self::MAX_REASONS);
        $more = $messages->count() - $shown->count();
        $lines = $shown->map(fn (string $m): string => '• '.e($m))->all();
        if ($more > 0) {
            $lines[] = e((string) __('admin.notify.more', ['count' => Num::toFa((string) $more)]));
        }

        self::failed(
            (string) __('admin.notify.failed_title'),
            (string) __('admin.notify.failed_hint').'<br>'.implode('<br>', $lines),
        );
    }
}
