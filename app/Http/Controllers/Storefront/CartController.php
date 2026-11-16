<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * سبد خرید: دیدن، افزودن، تغییر تعداد، حذف. همه با فورم‌های ساده (بی جاوااسکریپت هم کار می‌کند).
 * منطق کار در CartService است.
 */
class CartController extends Controller
{
    public function show(Request $request, CartService $carts): View
    {
        $cart = $carts->find($request);
        $lines = $cart === null ? collect() : $carts->lines($cart);
        $totals = $carts->totals($lines);

        return view('storefront.cart.show', [
            'lines' => $lines,
            'totals' => $totals,
            'estimate' => $lines->isEmpty() ? null : $carts->deliveryEstimate($totals['weight']),
        ]);
    }

    public function add(Request $request, CartService $carts): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $wanted = (int) ($data['qty'] ?? 1);

        try {
            $cart = $carts->resolve($request);
            $before = (int) $cart->items()->where('variant_id', $data['variant_id'])->value('qty');
            $final = $carts->add($cart, (int) $data['variant_id'], $wanted);
        } catch (CartException $e) {
            return back()->with('error', $e->userMessage());
        }

        $redirect = redirect()->route('cart.show')->with('status', __('cart.added'));
        if ($final < $before + $wanted) {
            $redirect->with('warning', __('cart.clamped', ['n' => Num::toFa((string) $final)]));
        }

        return $redirect;
    }

    public function update(Request $request, CartService $carts, int $item): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);
        $cart = $carts->find($request);

        try {
            if ($cart === null) {
                throw new CartException('not_found');
            }
            $final = $carts->setQty($cart, $item, (int) $data['qty']);
        } catch (CartException $e) {
            return redirect()->route('cart.show')->with('error', $e->userMessage());
        }

        $redirect = redirect()->route('cart.show');
        if ($final > 0 && $final < (int) $data['qty']) {
            $redirect->with('warning', __('cart.clamped', ['n' => Num::toFa((string) $final)]));
        }

        return $redirect;
    }

    public function remove(Request $request, CartService $carts, int $item): RedirectResponse
    {
        $cart = $carts->find($request);
        if ($cart !== null) {
            $carts->remove($cart, $item);
        }

        return redirect()->route('cart.show')->with('status', __('cart.removed'));
    }
}
