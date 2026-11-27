<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\SellerOrder;
use App\Services\StoreInfo;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * برگه‌ی بسته‌بندی برای چاپ و چسپاندن روی بسته (۶ قوس ۱۴۰۵، خواست صاحب پروژه).
 *
 * برای **هر فروشنده یک برگه** (هر فروشنده بسته‌ی خودش را آماده می‌کند): فرستنده (فروشنده)، گیرنده،
 * آدرس، روز تحویل، پولی که باید گرفته شود و لیست اجناس.
 *
 * **کد تحویل عمداً روی برگه نیست:** کد ثابت می‌کند جنس واقعاً به دست مشتری رسید؛ اگر روی بسته
 * چاپ شود، هر کس بسته را ببیند می‌تواند «تحویل شد» بزند.
 *
 * اجازه مثل دیدن سفارش (`OrderPolicy@view`): صاحب، مدیر، کارمند سفارش؛ پیک فقط سفارش خودش.
 */
class PackingSlipController extends Controller
{
    public function show(Request $request, string $order, StoreInfo $store): View
    {
        $row = Order::query()->where('uuid', $order)
            ->with(['province', 'collectionPoint', 'sellerOrders.seller.customer', 'sellerOrders.seller.province', 'sellerOrders.items'])
            ->firstOrFail();

        $user = $request->user();
        abort_if($user === null || ! $user->can('view', $row), 403);

        // بسته‌های لغوشده و خط‌هایی که همه‌اش لغو/برگشت شده چاپ نمی‌شوند
        $packages = $row->sellerOrders
            ->reject(fn (SellerOrder $so) => $so->status === Order::STATUS_CANCELLED)
            ->map(fn (SellerOrder $so) => [
                'number' => $so->number,
                'sender' => $this->sender($so, $store),
                'items' => $so->items->filter(fn (OrderItem $item) => $item->activeQty() > 0)->values(),
            ])
            ->filter(fn (array $package) => $package['items']->isNotEmpty())
            ->values();

        $due = Money::sub($row->total, $row->paid_total);

        return view('admin.packing-slip', [
            'order' => $row,
            'packages' => $packages,
            'due' => Money::compare($due, 0) > 0 ? $due : null,
            'paymentName' => PaymentMethod::query()->where('code', $row->payment_method_code)->first()?->tr('name') ?? $row->payment_method_code,
            'store' => $store,
        ]);
    }

    /**
     * فرستنده‌ی بسته: فروشنده (نام دکان، تلفن، آدرس) یا خود بازارک برای اجناس خودش.
     *
     * @return array{name: string, phone: ?string, address: ?string, platform: bool}
     */
    private function sender(SellerOrder $so, StoreInfo $store): array
    {
        $seller = $so->seller;
        if ($seller === null) {
            return ['name' => $store->name(), 'phone' => $store->phoneDisplay(), 'address' => $store->address(), 'platform' => true];
        }

        $phone = $seller->phone ?: $seller->customer?->phone;
        $address = collect([$seller->province?->tr('name'), $seller->city, $seller->address])
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')
            ->unique()
            ->implode('، ');

        return [
            'name' => (string) ($seller->store_name ?: $seller->business_name ?: $seller->full_name ?: $seller->customer?->name),
            'phone' => $phone ? Phone::display($phone) : null,
            'address' => $address !== '' ? $address : null,
            'platform' => false,
        ];
    }
}
