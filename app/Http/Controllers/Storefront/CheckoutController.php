<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Customer;
use App\Services\Cart\CartService;
use App\Services\Marketing\DiscountCodeException;
use App\Services\Marketing\DiscountCodeService;
use App\Services\Orders\CheckoutException;
use App\Services\Orders\CheckoutPage;
use App\Services\Orders\CheckoutService;
use App\Support\Money;
use App\Support\Phone;
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

    /** کد تخفیفی که مشتری در صفحه‌ی تسویه «اعمال» کرد (مرحله‌ی ۹-۳). مبلغش همیشه در سرور حساب می‌شود. */
    public const DISCOUNT_SESSION_KEY = 'checkout.discount_code';

    public function show(Request $request, CartService $carts, CheckoutPage $page, DiscountCodeService $discounts): View|RedirectResponse
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

        $data = $page->build($cart, $customer);
        $data['discount'] = null;
        $data['discountError'] = null;

        // کد تخفیف اعمال‌شده: دوباره بررسی (سبد، تاریخ یا ظرفیت ممکن است عوض شده باشد)
        $code = $request->session()->get(self::DISCOUNT_SESSION_KEY);
        if (is_string($code) && $code !== '') {
            $phone = Phone::normalize((string) $request->old('purchaser_phone')) ?? $customer?->phone;
            try {
                $quote = $discounts->quote($code, $data['totals']['subtotal'], $phone, $customer?->id);
                $data['discount'] = ['code' => $quote['code']->code, 'amount' => $quote['amount']];
            } catch (DiscountCodeException $e) {
                $request->session()->forget(self::DISCOUNT_SESSION_KEY);
                $data['discountError'] = $e->userMessage();
            }
        }

        return view('storefront.checkout.show', $data);
    }

    /**
     * دکمه‌ی «اعمال کد تخفیف»: کد بررسی و در نشست نگه داشته می‌شود؛ هرچه مشتری در فورم نوشته بود می‌ماند.
     */
    public function applyDiscount(Request $request, CartService $carts, DiscountCodeService $discounts): RedirectResponse
    {
        $input = $request->except(['_token', 'discount_code']);
        $back = redirect()->route('checkout.show')->withInput($input);

        $cart = $carts->find($request);
        $lines = $cart === null ? collect() : $carts->lines($cart);
        if ($cart === null || $lines->isEmpty()) {
            return redirect()->route('cart.show')->with('error', __('checkout.errors.empty_cart'));
        }

        $raw = mb_substr(trim((string) $request->input('discount_code')), 0, 60);
        $phone = Phone::normalize((string) $request->input('purchaser_phone')) ?? $request->user('customer')?->phone;

        try {
            $quote = $discounts->quote($raw, $carts->totals($lines)['subtotal'], $phone, $request->user('customer')?->id);
        } catch (DiscountCodeException $e) {
            $request->session()->forget(self::DISCOUNT_SESSION_KEY);

            return $back->withErrors(['discount_code' => $e->userMessage()], 'discount');
        }

        $request->session()->put(self::DISCOUNT_SESSION_KEY, $quote['code']->code);

        return $back->with('status', __('checkout.discount.applied', [
            'code' => $quote['code']->code,
            'amount' => Money::format($quote['amount']),
        ]));
    }

    public function removeDiscount(Request $request): RedirectResponse
    {
        $request->session()->forget(self::DISCOUNT_SESSION_KEY);

        return redirect()->route('checkout.show')
            ->withInput($request->except(['_token', 'discount_code']))
            ->with('status', __('checkout.discount.removed'));
    }

    public function place(CheckoutRequest $request, CartService $carts, CheckoutService $checkout): RedirectResponse
    {
        $cart = $carts->find($request);
        if ($cart === null) {
            return redirect()->route('cart.show')->with('error', __('checkout.errors.empty_cart'));
        }

        try {
            $order = $checkout->place($cart, $request->toCheckoutData());
        } catch (DiscountCodeException $e) {
            // کد تخفیف دیگر قابل استفاده نیست: سفارش با مبلغ دیگر ثبت نمی‌شود؛ مشتری مبلغ تازه را می‌بیند
            $request->session()->forget(self::DISCOUNT_SESSION_KEY);

            return redirect()->route('checkout.show')->withInput()
                ->with('error', __('checkout.discount.not_placed', ['reason' => $e->userMessage()]));
        } catch (CheckoutException $e) {
            return back()->withInput()->with('error', $e->userMessage());
        }
        $request->session()->forget(self::DISCOUNT_SESSION_KEY);

        // سفارش‌های همین نشست (حداکثر ۱۰ تا) تا مهمان بتواند بعداً پیدایش کند
        $recent = array_values(array_unique(array_merge([$order->uuid], (array) $request->session()->get(self::RECENT_SESSION_KEY, []))));
        $request->session()->put(self::RECENT_SESSION_KEY, array_slice($recent, 0, 10));

        return redirect()->route('order.show', ['uuid' => $order->uuid])->with('placed', true);
    }
}
