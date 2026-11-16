<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * نمایش صفحه‌ی ثابت: /page/{slug}
 * صفحه‌ی غیرفعال یا بی‌متن به مشتری نشان داده نمی‌شود (۴۰۴).
 */
class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->active()->where('slug', $slug)->first();

        abort_if($page === null || ! $page->hasBody(), 404);

        // متن از ویرایشگر پنل می‌آید؛ پیش از نمایش فقط تگ‌های بی‌خطر نگه داشته می‌شود
        $body = Str::sanitizeHtml($page->tr('body'));

        $description = $page->tr('meta_description')
            ?: Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($body))), 155);

        return view('storefront.page', [
            'page' => $page,
            'body' => $body,
            'description' => $description,
        ]);
    }
}
