<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Models\Customer;
use App\Models\Province;
use App\Rules\AfghanMobile;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قدم ۲ ثبت‌نام فروشنده: آدرس دکان یا گدام.
 */
class SellerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));
        $this->merge([
            'city' => trim((string) $this->input('city')),
            'address' => trim((string) $this->input('address')),
            'phone' => $phone === '' ? null : (Phone::normalize($phone) ?? $phone),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'province_id' => ['required', 'integer', Rule::exists(Province::class, 'id')->where('is_active', true)],
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20', new AfghanMobile],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) __('seller.fields');
    }
}
