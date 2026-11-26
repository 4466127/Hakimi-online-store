<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Support\LocalAvatarProvider;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Services\Settings;
use App\Services\StoreInfo;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * پنل مدیریت فروشگاه در آدرس /admin (برای اعضای خانواده).
 * ورود با شماره‌ی تلفن؛ فونت و همه‌ی فایل‌ها از خود سرور (بدون CDN).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            // ورود دومرحله‌ای با برنامه‌ی کد (Google Authenticator، Microsoft Authenticator…)؛ از «حساب من» روشن می‌شود.
            // ۸ کد پشتیبان یک‌بارمصرف برای وقتی که موبایل گم شد. ADMIN_MFA_REQUIRED=true همه را مجبور می‌کند (پیشنهاد برای سرور).
            ->multiFactorAuthentication([
                // نام فروشگاه در برنامه‌ی کد از brandName پنل گرفته می‌شود
                AppAuthentication::make()->recoverable(),
            ])
            ->requiresMultiFactorAuthentication((bool) config('store.security.admin_mfa_required', false))
            ->brandName(fn (): string => (string) app(Settings::class)->get('store.name'))
            // لوگوی بازارک (WebP)؛ اگر مدیر لوگوی دیگری گذاشته باشد همان
            ->brandLogo(fn (): string => app(StoreInfo::class)->logoUrl())
            ->darkModeBrandLogo(fn (): string => app(StoreInfo::class)->logoDarkUrl())
            ->brandLogoHeight('2.5rem')
            ->favicon('/favicon.png')
            // فیروزه‌ای کیف لوگوی بازارک
            ->colors([
                'primary' => Color::hex('#0e8c8c'),
            ])
            ->font('Vazirmatn', url: '/fonts/vazirmatn/vazirmatn.css', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->navigationGroups([
                NavigationGroup::make(fn (): string => (string) __('admin.nav.catalog')),
                NavigationGroup::make(fn (): string => (string) __('admin.nav.marketplace')),
                NavigationGroup::make(fn (): string => (string) __('admin.nav.content')),
                NavigationGroup::make(fn (): string => (string) __('admin.nav.system')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // کارت «قدم‌های راه‌اندازی» (GettingStartedWidget) خودکار از پوشه‌ی Widgets پیدا می‌شود
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
