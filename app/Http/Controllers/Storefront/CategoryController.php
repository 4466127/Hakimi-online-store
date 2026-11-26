<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\ListingFilters;
use App\Services\Catalog\ProductCatalog;
use App\Services\CategoryMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحه‌ی همه‌ی دسته‌ها (برای موبایل) و صفحه‌ی یک دسته.
 * صفحه‌ی دسته اجناس همان دسته و زیردسته‌هایش را با فیلتر و مرتب‌سازی نشان می‌دهد.
 */
class CategoryController extends Controller
{
    /**
     * همه‌ی دسته‌ها. در موبایل دو ستون مثل دیجی‌کالا: دسته‌های اصلی در نوار کنار، زیردسته‌های دسته‌ی انتخاب‌شده
     * در کنارش. `?c=slug` دسته‌ی انتخاب‌شده است (بی جاوااسکریپت هم کار می‌کند؛ با Alpine بی بارگذاری دوباره).
     */
    public function index(Request $request, CategoryMenu $menu): View
    {
        $tree = $menu->tree();
        $wanted = (string) $request->query('c', '');
        $selected = 0;
        foreach ($tree as $i => $root) {
            if ($root['slug'] === $wanted) {
                $selected = $i;
                break;
            }
        }

        return view('storefront.categories.index', ['tree' => $tree, 'selected' => $selected]);
    }

    public function show(Request $request, string $slug, ProductCatalog $catalog): View
    {
        $category = Category::query()->where('slug', $slug)->first();
        abort_if($category === null || ! $category->isVisible(), 404);

        $filters = ListingFilters::fromRequest($request);

        return view('storefront.categories.show', [
            'category' => $category,
            'ancestors' => $category->ancestors(),
            'children' => $category->children()->active()->get(),
            'filters' => $filters,
            'products' => $catalog->paginate($category, $filters),
            'facets' => $catalog->facets($category, $filters),
        ]);
    }
}
