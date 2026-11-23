<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * نقشه‌ی سایت برای گوگل (sitemap.xml): صفحه‌ی اصلی، دسته‌های دیده‌شدنی، اجناس منتشرشده و صفحه‌های ثابت فعال.
 *
 * - جنس فقط وقتی هست که منتشر شده (فعال، تاریخ انتشار گذشته، فروشنده‌اش تأییدشده) و دسته‌اش
 *   (با همه‌ی بالادست‌ها) فعال باشد؛ همان قاعده‌ی صفحه‌ی جنس. پیش‌نویس، غیرفعال و حذف‌شده هرگز نیست.
 * - نتیجه یک روز در cache می‌ماند و با هر ذخیره/حذف جنس، دسته، صفحه یا فروشنده پاک می‌شود
 *   (AppServiceProvider)، پس تغییرها در درخواست بعدی دیده می‌شود.
 */
class Sitemap
{
    public const CACHE_KEY = 'seo.sitemap.xml';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function xml(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), fn (): string => $this->build());
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    public function entries(): array
    {
        $entries = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('categories'), 'lastmod' => null],
            ['loc' => route('seller.landing'), 'lastmod' => null],
            ['loc' => route('seller.commissions'), 'lastmod' => null],
        ];

        $categories = Category::query()->get(['id', 'slug', 'path', 'is_active', 'updated_at']);
        $active = $categories->where('is_active', true)->pluck('id')->flip();
        $visibleIds = [];
        foreach ($categories as $category) {
            if (! $category->is_active) {
                continue;
            }
            $ancestorsActive = collect($category->ancestorIds())->every(fn (int $id): bool => $active->has($id));
            if ($ancestorsActive) {
                $visibleIds[] = $category->id;
                $entries[] = ['loc' => $category->url(), 'lastmod' => $category->updated_at?->toAtomString()];
            }
        }

        if ($visibleIds !== []) {
            Product::query()->published()
                ->whereIn('products.category_id', $visibleIds)
                ->orderBy('products.id')
                ->select(['products.id', 'products.slug', 'products.updated_at'])
                ->chunk(500, function ($products) use (&$entries): void {
                    foreach ($products as $product) {
                        $entries[] = ['loc' => $product->url(), 'lastmod' => $product->updated_at?->toAtomString()];
                    }
                });
        }

        foreach (Page::query()->active()->orderBy('position')->get(['id', 'slug', 'updated_at']) as $page) {
            $entries[] = ['loc' => $page->url(), 'lastmod' => $page->updated_at?->toAtomString()];
        }

        return $entries;
    }

    private function build(): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($this->entries() as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if ($entry['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$entry['lastmod'].'</lastmod>';
            }
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
