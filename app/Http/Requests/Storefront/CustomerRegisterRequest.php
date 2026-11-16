<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Rules\AfghanMobile;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ثبت‌نام مشتری: نام، شماره‌ی موبایل افغانستان، رمز.
 */
class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** شماره پیش از بررسی «تکراری نبودن» به شکل استاندارد درمی‌آید. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => Phone::normalize((string) $this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:20', new AfghanMobile, Rule::unique('customers', 'phone')],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.unique' => __('customer.register.phone_taken'),
            'terms.accepted' => __('customer.register.terms_required'),
        ];
    }
}
