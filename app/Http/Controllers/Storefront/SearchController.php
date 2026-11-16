<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ListingFilters;
use App\Services\Catalog\ProductCatalog;
use App\Services\CategoryMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * جستجو در نام، برند، دسته، SKU و مشخصات؛ با همان فیلتر و مرتب‌سازی صفحه‌ی دسته.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, ProductCatalog $catalog, CategoryMenu $menu): View
    {
        $filters = ListingFilters::fromRequest($request, 'relevance');

        if (! $filters->hasSearch()) {
            // جستجوی خالی یا یک‌حرفی: دسته‌ها و تازه‌ترین‌ها را نشان بده
            return view('storefront.search', [
                'filters' => $filters,
                'products' => null,
                'facets' => null,
                'categories' => collect(),
                'tree' => $menu->tree(),
                'latest' => $catalog->home(false, 8),
            ]);
        }

        return view('storefront.search', [
            'filters' => $filters,
            'products' => $catalog->paginate(null, $filters),
            'facets' => $catalog->facets(null, $filters),
            'categories' => $catalog->matchingCategories($filters),
            'tree' => [],
            'latest' => collect(),
        ]);
    }
}
