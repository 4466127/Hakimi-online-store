<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\CategoryMenu;
use App\Services\StoreInfo;
use Illuminate\Contracts\View\View;

/**
 * صفحه‌ی اصلی فروشگاه.
 * (مرحله‌ی ۲: معرفی و «به‌زودی»؛ مرحله‌ی ۳: بنرها، دسته‌ها و اجناس.)
 */
class HomeController extends Controller
{
    public function __invoke(StoreInfo $store, CategoryMenu $menu): View
    {
        return view('storefront.home', ['store' => $store, 'categories' => $menu->tree()]);
    }
}
