<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Storefront\SeoController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * هدرهای امنیتی پایه روی همه‌ی صفحه‌ها (ARCHITECTURE.md بخش ۹-۵).
 * HSTS فقط روی HTTPS فرستاده می‌شود.
 *
 * CSP (مرحله‌ی ۸، پیش از پرداخت آنلاین): مرورگر فقط فایل‌های خود سایت را اجرا می‌کند. اگر روزی کسی
 * کد جاوااسکریپت را در صفحه جا داد (XSS)، آن کد نمی‌تواند از سایت بیرونی بار شود یا اطلاعات را به
 * سایت دیگری بفرستد. دو سیاست جدا:
 *  - فروشگاه: سخت (فقط خود سایت + کاشی نقشه + صفحه‌ی پرداخت حساب‌پی). Alpine به 'unsafe-eval' نیاز دارد.
 *  - پنل مدیریت (Filament/Livewire): اسکریپت داخل صفحه زیاد دارد، پس فقط قاب، base و فورم محدود می‌شود.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // سایت در قاب (iframe) سایت دیگر باز نشود (جلوگیری از clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // مرورگر نوع فایل را حدس نزند
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // آدرس کامل صفحه به سایت‌های دیگر فرستاده نشود
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // دوربین و میکروفون لازم نیست. موقعیت (GPS) فقط برای خود سایت: دکمه‌ی «جای من» روی نقشه‌ی تسویه
        // (پیش از این geolocation=() بود و آن دکمه را در مرورگر کاملاً بسته بود)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->isPanel($request)
                ? $this->panelPolicy()
                : $this->storefrontPolicy($request));
        }

        // صفحه‌های خصوصی (پنل، سبد، تسویه، حساب، سفارش) در گوگل نیایند؛ محافظت اصلی‌شان ورود و اجازه است
        if ($this->isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /** پنل مدیریت و درخواست‌های Livewire آن (و رسیدهای چاپی زیر /admin). */
    private function isPanel(Request $request): bool
    {
        $first = explode('/', ltrim($request->path(), '/'))[0];

        return in_array($first, ['admin', 'livewire', 'filament'], true);
    }

    private function panelPolicy(): string
    {
        return implode('; ', [
            "base-uri 'self'",
            "object-src 'none'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    private function storefrontPolicy(Request $request): string
    {
        $self = "'self'";
        $scripts = [$self, "'unsafe-eval'"];
        $styles = [$self, "'unsafe-inline'"];
        $connect = [$self];

        // روی لپتاپ وقتی `npm run dev` روشن است، فایل‌ها از سرور Vite می‌آیند
        if (app()->environment('local') && Vite::isRunningHot()) {
            $hot = rtrim(trim((string) @file_get_contents(Vite::hotFile())), '/');
            if ($hot !== '') {
                $scripts[] = $hot;
                $styles[] = $hot;
                $connect[] = $hot;
                $connect[] = preg_replace('/^http/', 'ws', $hot);
            }
        }

        // کاشی نقشه‌ی تسویه (OpenStreetMap یا آنچه در .env آمده)
        $images = [$self, 'data:', 'blob:'];
        $tileHost = parse_url((string) config('store.maps.tile_url'), PHP_URL_HOST);
        if (is_string($tileHost) && $tileHost !== '') {
            $images[] = 'https://'.str_replace(['{s}.', '{s}'], ['*.', '*'], $tileHost);
        }

        // بعد از ثبت سفارش، مرورگر به صفحه‌ی پرداخت حساب‌پی فرستاده می‌شود (Chrome این را هم با form-action می‌سنجد)
        $forms = [$self];
        $checkoutHosts = (array) config('services.hesabpay.checkout_hosts', []);
        if ($checkoutHosts === []) {
            $forms[] = 'https:';
        } else {
            foreach ($checkoutHosts as $host) {
                $host = ltrim((string) $host, '.');
                $forms[] = 'https://'.$host;
                $forms[] = 'https://*.'.$host;
            }
        }

        $policy = [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            'img-src '.implode(' ', $images),
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            "media-src 'self' blob:",
            "manifest-src 'self'",
            "worker-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            'form-action '.implode(' ', $forms),
            "frame-ancestors 'self'",
        ];

        if ($request->isSecure()) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $policy);
    }

    private function isPrivate(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');
        foreach (SeoController::PRIVATE_PATHS as $prefix) {
            $bare = rtrim($prefix, '/');
            if ($path === $bare || str_starts_with($path, $bare.'/')) {
                return true;
            }
        }

        return false;
    }
}
