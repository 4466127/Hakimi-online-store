<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Customer;
use App\Services\Cart\CartService;
use App\Services\Orders\CheckoutException;
use App\Services\Orders\CheckoutPage;
use App\Services\Orders\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحه‌ی تسویه و ثبت سفارش (مرحله‌ی ۲-۲).
 *
 * کنترلر نازک: داده‌ی صفحه از `CheckoutPage` و ثبت سفارش از `CheckoutService` می‌آید.
 * هیچ مبلغی از فورم خوانده نمی‌شود؛ خطای سرویس (موجودی، روز تحویل، روش پرداخت) به مشتری نشان داده می‌شود.
 */
class CheckoutController extends Controller
{
    /** چند سفارش آخر همین نشست (برای پیدا کردن سفارش مهمان بی ثبت‌نام). */
    public const RECENT_SESSION_KEY = 'recent_orders';

    public function show(Request $request, CartService $carts, CheckoutPage $page): View|RedirectResponse
    {
        $cart = $carts->find($request);
        $lines = $cart === null ? collect() : $carts->lines($cart);

        if ($cart === null || $lines->isEmpty()) {
            return redirect()->route('cart.show')->with('error', __('checkout.errors.empty_cart'));
        }
        // جنس ناموجود یا کم‌موجود: اول در سبد درست شود
        if ($lines->contains(fn ($line) => $line->problem !== null)) {
            return redirect()->route('cart.show')->with('warning', __('cart.problem_banner'));
        }

        /** @var Customer|null $customer */
        $customer = $request->user('customer');

        return view('storefront.checkout.show', $page->build($cart, $customer));
    }

    public function place(CheckoutRequest $request, CartService $carts, CheckoutService $checkout): RedirectResponse
    {
        $cart = $carts->find($request);
        if ($cart === null) {
            return redirect()->route('cart.show')->with('error', __('checkout.errors.empty_cart'));
        }

        try {
            $order = $checkout->place($cart, $request->toCheckoutData());
        } catch (CheckoutException $e) {
            return back()->withInput()->with('error', $e->userMessage());
        }

        // سفارش‌های همین نشست (حداکثر ۱۰ تا) تا مهمان بتواند بعداً پیدایش کند
        $recent = array_values(array_unique(array_merge([$order->uuid], (array) $request->session()->get(self::RECENT_SESSION_KEY, []))));
        $request->session()->put(self::RECENT_SESSION_KEY, array_slice($recent, 0, 10));

        return redirect()->route('order.show', ['uuid' => $order->uuid])->with('placed', true);
    }
}
