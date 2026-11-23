<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Storefront\SeoController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هدرهای امنیتی پایه روی همه‌ی صفحه‌ها (ARCHITECTURE.md بخش ۹-۵).
 * HSTS فقط روی HTTPS فرستاده می‌شود. CSP کامل در مرحله‌ی ۶ (بازبینی امنیتی) اضافه می‌شود.
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
        // دسترسی به دوربین، میکروفون و موقعیت لازم نیست
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // صفحه‌های خصوصی (پنل، سبد، تسویه، حساب، سفارش) در گوگل نیایند؛ محافظت اصلی‌شان ورود و اجازه است
        if ($this->isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
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
