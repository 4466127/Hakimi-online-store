<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Seo\Sitemap;
use Illuminate\Http\Response;

/**
 * sitemap.xml و robots.txt (مرحله‌ی ۹-۱).
 * robots.txt فقط از گوگل خواهش می‌کند؛ امنیت نیست. صفحه‌های خصوصی با ورود و اجازه محافظت می‌شوند
 * و هدر X-Robots-Tag: noindex هم می‌گیرند (SecurityHeaders).
 */
class SeoController extends Controller
{
    /** مسیرهایی که گوگل نباید بخواند (همان‌ها که SecurityHeaders noindex می‌دهد). */
    public const PRIVATE_PATHS = [
        '/admin', '/livewire', '/cart', '/checkout', '/account', '/order/', '/payment/', '/webhooks/', '/track',
        '/login', '/register', '/logout', '/sell/onboarding', '/search',
    ];

    public function sitemap(Sitemap $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (self::PRIVATE_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
