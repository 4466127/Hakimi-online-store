<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Models\Customer;
use App\Rules\AfghanMobile;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * ورود مشتری. بعد از ۵ تلاش نادرست برای یک شماره (از یک IP)، یک دقیقه صبر.
 */
class CustomerLoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', new AfghanMobile],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** مشتری را پیدا و رمز را بررسی می‌کند؛ اگر درست نبود خطا می‌دهد. */
    public function authenticate(): Customer
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $customer = Customer::query()->where('phone', Phone::normalize($this->input('phone')))->first();

        if ($customer === null || $customer->password === null || ! Hash::check((string) $this->input('password'), $customer->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages(['phone' => __('customer.login.inactive')]);
        }

        RateLimiter::clear($key);

        return $customer;
    }

    private function throttleKey(): string
    {
        return 'customer-login:'.(Phone::normalize($this->input('phone')) ?? '-').'|'.$this->ip();
    }
}
