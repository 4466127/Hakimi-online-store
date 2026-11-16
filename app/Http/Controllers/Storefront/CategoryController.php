<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryMenu;
use Illuminate\View\View;

/**
 * صفحه‌ی همه‌ی دسته‌ها (برای موبایل) و صفحه‌ی یک دسته.
 * اجناس هر دسته در مرحله‌ی ۳ (کاتالوگ) به همین صفحه اضافه می‌شود.
 */
class CategoryController extends Controller
{
    public function index(CategoryMenu $menu): View
    {
        return view('storefront.categories.index', ['tree' => $menu->tree()]);
    }

    public function show(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->first();
        abort_if($category === null || ! $category->isVisible(), 404);

        return view('storefront.categories.show', [
            'category' => $category,
            'ancestors' => $category->ancestors(),
            'children' => $category->children()->active()->get(),
        ]);
    }
}
