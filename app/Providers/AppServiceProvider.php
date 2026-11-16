<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Cart\CartService;
use App\Services\CategoryMenu;
use App\Services\Settings;
use App\Services\StoreInfo;
use App\Support\AdminNotify;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تنظیمات فروشگاه در هر درخواست فقط یک بار خوانده شود
        $this->app->singleton(Settings::class);
        $this->app->singleton(CategoryMenu::class);
        // سبد: شمارنده‌ی سربرگ در هر درخواست فقط یک بار حساب شود
        $this->app->scoped(CartService::class);
    }

    public function boot(): void
    {
        // در محیط توسعه اشتباه‌های پنهان (N+1، ستون ناموجود) خطا شود تا زود دیده شود
        Model::shouldBeStrict(! $this->app->isProduction());

        // قاعده‌ی رمز همه‌ی کاربران پنل: حداقل ۸ حرف، با حرف و عدد
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // همه‌ی صفحه‌های فروشگاه $store (نام، لوگو، تماس) را دارند
        View::composer('storefront.*', fn ($view) => $view->with('store', app(StoreInfo::class)));

        // ورود و ثبت‌نام مشتری: حداکثر ۱۰ درخواست در دقیقه از یک IP
        RateLimiter::for('customer-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // جستجو: ۶۰ بار در دقیقه از یک IP (جلوی فشار آوردن به دیتابیس)
        RateLimiter::for('storefront-search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // سبد: ۶۰ تغییر در دقیقه از یک IP
        RateLimiter::for('storefront-cart', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // صفحه‌بندی فارسی و سبک برای سایت مشتری (پنل مدیریت صفحه‌بندی خودش را دارد)
        Paginator::defaultView('vendor.pagination.bazarak');

        $this->registerAdminNotifications();
        $this->registerAdminLoginGuards();
    }

    /**
     * اعلان‌های پنل (تصمیم صاحب پروژه): موفقیت سبز ۸ ثانیه؛ خطا سرخ و با دلیل.
     * - هر اعلان پیش‌فرض ۸ ثانیه می‌ماند.
     * - خطای فورم (قاعده‌های Filament) اعلان سرخ با دلیل‌ها می‌دهد؛ پیش از این فقط زیر خانه‌ها نوشته می‌شد
     *   و اگر آن خانه در بخش بسته یا پایین صفحه بود، مدیر می‌دید «ذخیره زدم و هیچ اتفاقی نیفتاد».
     * - تغییر سوییچ جدول («فعال») «ذخیره شد» می‌گوید.
     */
    private function registerAdminNotifications(): void
    {
        Notification::configureUsing(fn (Notification $notification) => $notification->duration(AdminNotify::SUCCESS_MS));

        // وسط بالای صفحه (نه گوشه‌ی دور)، تا مدیر حتماً ببیند
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);

        BasePage::$reportValidationErrorUsing = function (ValidationException $exception): void {
            // اعتبارسنجی زنده‌ی یک خانه (هنگام رد شدن از آن) اعلان نمی‌خواهد؛ فقط دکمه‌ی «ذخیره»
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
                if (($frame['function'] ?? '') === 'validateOnly') {
                    return;
                }
            }
            AdminNotify::validationFailed($exception);
        };

        ToggleColumn::configureUsing(fn (ToggleColumn $column) => $column
            ->afterStateUpdated(fn () => AdminNotify::done((string) __('admin.saved'))));
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
