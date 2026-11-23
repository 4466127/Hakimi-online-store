<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Marketing\CampaignAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * برچسب تبلیغ (utm_*) را هنگام ورود از لینک تبلیغ در کوکی ۳۰ روزه نگه می‌دارد (مرحله‌ی ۹-۲).
 * فقط درخواست‌های GET؛ اولین برچسب درست می‌ماند.
 */
class CaptureCampaign
{
    public function __construct(private readonly CampaignAttribution $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $request->query->has('utm_source')) {
            $cookie = $this->attribution->cookieFor($request);
            if ($cookie !== null) {
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }
}
