<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerLoginRequest;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Models\Customer;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * ورود، ثبت‌نام و خروج مشتری با شماره‌ی تلفن و رمز.
 * (تأیید شماره با پیامک وقتی سرویس پیامک واقعی گرفته شد اضافه می‌شود — مرحله‌ی ۵.)
 */
class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('storefront.auth.login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $customer = $request->authenticate();

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        $customer->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('account'));
    }

    public function showRegister(): View
    {
        // لینک «خواندن شرایط» فقط اگر صفحه‌ی شرایط نوشته شده باشد
        $terms = Page::query()->active()->where('slug', 'terms')->first();

        return view('storefront.auth.register', ['terms' => $terms?->hasBody() ? $terms : null]);
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse
    {
        $customer = Customer::create([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();
        $customer->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('account'))
            ->with('status', __('customer.register.welcome', ['name' => $customer->name]));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        // فقط معلومات مشتری پاک شود؛ اگر مدیر هم در همین مرورگر وارد است بیرون نشود
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
