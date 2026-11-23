<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * صفحه‌ی سفارش مشتری، پیگیری با «شماره‌ی سفارش + موبایل» و «سفارش‌های من» (مرحله‌ی ۲-۲).
 *
 * - آدرس صفحه‌ی سفارش با uuid است (حدس‌زدنی نیست)؛ مهمان هم با همان لینک سفارشش را می‌بیند.
 * - پیگیری: شماره‌ی سفارش با شماره‌ی خریدار **یا** گیرنده می‌خواند (گیرنده هم حق دیدن دارد).
 * - هیچ معلومات فروشنده یا مالی (کمیسیون، سهم فروشنده) به مشتری نشان داده نمی‌شود.
 */
class OrderController extends Controller
{
    public function show(Request $request, string $uuid): View
    {
        $order = Order::query()
            ->with(['items', 'province', 'collectionPoint', 'latestPayment'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return view('storefront.orders.show', [
            'order' => $order,
            'method' => PaymentMethod::query()->where('code', $order->payment_method_code)->first(),
            'justPlaced' => (bool) $request->session()->get('placed', false),
        ]);
    }

    public function trackForm(): View
    {
        return view('storefront.orders.track');
    }

    /** شماره‌ی سفارش + موبایل ← صفحه‌ی سفارش. پیام یکسان برای «نبود» و «موبایل نمی‌خواند». */
    public function track(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $number = Order::normalizeNumber($data['number']);
        $phone = Phone::normalize($data['phone']);

        $order = $number === null || $phone === null ? null : Order::query()
            ->where('number', $number)
            ->where(fn ($q) => $q->where('purchaser_phone', $phone)->orWhere('recipient_phone', $phone))
            ->first();

        if ($order === null) {
            throw ValidationException::withMessages(['number' => __('checkout.track.not_found')]);
        }

        return redirect()->route('order.show', ['uuid' => $order->uuid]);
    }

    /** «سفارش‌های من» برای مشتری عضو. */
    public function mine(Request $request): View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return view('storefront.orders.mine', [
            'customer' => $customer,
            'orders' => Order::query()
                ->where('customer_id', $customer->id)
                ->withCount('items')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
