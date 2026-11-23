<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * حساب کاربری مشتری: خلاصه، سه سفارش آخر، ویرایش نام و تغییر رمز.
 * آدرس‌های ذخیره‌شده بعد از MVP اضافه می‌شود.
 */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return view('storefront.account.show', [
            'customer' => $customer,
            'seller' => $customer->seller,
            'orders' => Order::query()->where('customer_id', $customer->id)->orderByDesc('id')->limit(3)->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:customers,email,'.$customer->id],
        ]);

        $customer->update(['name' => trim($data['name']), 'email' => $data['email'] ?? null]);

        return back()->with('status', __('customer.account.profile_saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], (string) $customer->password)) {
            throw ValidationException::withMessages(['current_password' => __('auth.password')]);
        }

        $customer->update(['password' => $data['password']]);

        return back()->with('status', __('customer.account.password_saved'));
    }
}
