<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerAddressBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «آدرس‌های من» در حساب مشتری (۶ قوس ۱۴۰۵): دیدن و پاک کردن آدرس‌های ذخیره‌شده.
 * آدرس تازه در صفحه‌ی تسویه نوشته می‌شود و بعد از سفارش خودش این‌جا می‌آید.
 */
class AddressController extends Controller
{
    public function index(Request $request, CustomerAddressBook $book): View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return view('storefront.account.addresses', [
            'addresses' => $book->list($customer),
            'max' => CustomerAddressBook::MAX,
        ]);
    }

    public function destroy(Request $request, CustomerAddressBook $book, int $address): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        // آدرس مشتری دیگر: مثل «پیدا نشد» (نه اینکه بگوییم مال کس دیگری است)
        abort_unless($book->delete($customer, $address), 404);

        return redirect()->route('account.addresses')->with('status', __('customer.addresses.deleted'));
    }
}
