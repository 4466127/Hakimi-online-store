<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\DiscountCode;
use App\Models\HomeVideo;
use App\Services\Catalog\ProductCatalog;
use App\Services\CategoryMenu;
use App\Services\StoreInfo;
use Illuminate\Contracts\View\View;

/**
 * صفحه‌ی اصلی فروشگاه.
 * بنرها، کد تخفیف سایت، معرفی، دسته‌ها، ویدیوها، «پیشنهاد ویژه» و «تازه‌ترین‌ها».
 */
class HomeController extends Controller
{
    public function __invoke(StoreInfo $store, CategoryMenu $menu, ProductCatalog $catalog): View
    {
        $featured = $catalog->home(true);

        return view('storefront.home', [
            'store' => $store,
            'categories' => $menu->tree(),
            // بنرهای روشن به ترتیب (حداکثر ۵؛ مرحله‌ی ۹-۴)
            'banners' => Banner::query()->forHome()->get(),
            // کد تخفیف سایت با کاپی و شمارش معکوس، و ویدیوهای اجناس (۵ قوس ۱۴۰۵)
            'promo' => DiscountCode::sitePromo(),
            'videos' => HomeVideo::query()->forHome()->get(),
            'featured' => $featured,
            // تازه‌ها: همانی که در «ویژه» آمده تکرار نشود
            'latest' => $catalog->home(false, 8 + $featured->count())->reject(fn ($p) => $featured->contains('id', $p->id))->take(8)->values(),
        ]);
    }
}
