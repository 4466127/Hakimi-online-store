<?php

use App\Http\Middleware\CaptureCampaign;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // هدرهای امنیتی روی همه‌ی صفحه‌ها (فروشگاه و پنل مدیریت)
        $middleware->append(SecurityHeaders::class);
        // برچسب لینک تبلیغ (utm) در کوکی رمزشده‌ی ۳۰ روزه (مرحله‌ی ۹-۲؛ بعد از رمزگذاری کوکی‌ها)
        $middleware->web(append: [CaptureCampaign::class]);

        // مشتریِ واردنشده ← صفحه‌ی ورود فروشگاه؛ مشتریِ واردشده که به ورود/ثبت‌نام رفت ← حساب
        // (پنل مدیریت مسیر ورود خودش را دارد و به این دو دست نمی‌زند)
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
