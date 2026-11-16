<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحه‌ی معرفی «در بازارک بفروشید» (برای همه، بی ورود).
 */
class SellerController extends Controller
{
    public function landing(Request $request): View
    {
        $customer = $request->user('customer');

        return view('storefront.seller.landing', [
            'seller' => $customer instanceof Customer ? $customer->seller : null,
        ]);
    }
}
