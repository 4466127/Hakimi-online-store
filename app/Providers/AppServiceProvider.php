<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تنظیمات فروشگاه در هر درخواست فقط یک بار خوانده شود
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // در محیط توسعه اشتباه‌های پنهان (N+1، ستون ناموجود) خطا شود تا زود دیده شود
        Model::shouldBeStrict(! $this->app->isProduction());

        // قاعده‌ی رمز همه‌ی کاربران پنل: حداقل ۸ حرف، با حرف و عدد
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->registerAdminLoginGuards();
    }

    /**
     * شمارش رمزهای اشتباه مدیران و قفل موقت حساب؛ ثبت ورود موفق.
     */
    private function registerAdminLoginGuards(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            $user = $event->user;
            if (! $user instanceof User) {
                return;
            }

            $attempts = $user->failed_logins + 1;
            $limit = (int) config('store.admin_lock_after_attempts', 10);

            if ($attempts >= $limit) {
                $user->forceFill([
                    'failed_logins' => 0,
                    'locked_until' => now()->addMinutes((int) config('store.admin_lock_minutes', 15)),
                ])->saveQuietly();

                app(ActivityLogger::class)->log('auth.locked', $user, null, $user);

                return;
            }

            $user->forceFill(['failed_logins' => $attempts])->saveQuietly();
        });

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if (! $user instanceof User) {
                return;
            }

            $user->forceFill([
                'failed_logins' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ])->saveQuietly();

            app(ActivityLogger::class)->log('auth.login', $user, null, $user);
        });
    }
}
