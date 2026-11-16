<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CategoryMenu;
use App\Services\Commission\CommissionService;
use App\Services\Commission\SellerIncentives;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحه‌های عمومی «در بازارک بفروشید» (برای همه، بی ورود): معرفی و نرخ کمیسیون.
 */
class SellerController extends Controller
{
    public function landing(Request $request, SellerIncentives $incentives): View
    {
        $customer = $request->user('customer');

        return view('storefront.seller.landing', [
            'seller' => $customer instanceof Customer ? $customer->seller : null,
            'incentives' => $incentives->summary(),
        ]);
    }

    /** نرخ کمیسیون هر دسته (فروشنده پیش از گذاشتن جنس می‌بیند) و قاعده‌های کمیسیون. */
    public function commissions(CommissionService $commission, CategoryMenu $menu): View
    {
        return view('storefront.seller.commissions', [
            'rows' => $commission->publicRateTable($menu->tree()),
            'defaultRate' => $commission->defaultRate(),
        ]);
    }
}
