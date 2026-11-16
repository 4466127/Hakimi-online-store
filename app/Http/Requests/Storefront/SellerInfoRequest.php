<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قدم ۱ ثبت‌نام فروشنده: نوع فروشنده و معلومات تکمیلی.
 * به‌جای «کد ملی» (ایران)، نمبر تذکره‌ی افغانستان (الکترونیکی یا کاغذی) گرفته می‌شود.
 */
class SellerInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'father_name' => trim((string) $this->input('father_name')),
            'store_name' => trim((string) $this->input('store_name')),
            'tazkira_number' => Seller::normalizeTazkira((string) $this->input('tazkira_number')),
            'is_local_producer' => $this->boolean('is_local_producer'),
            'is_social_seller' => $this->boolean('is_social_seller'),
            'has_brand' => $this->boolean('has_brand'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->user('customer');
        $sellerId = $customer->seller?->id;

        return [
            'type' => ['required', Rule::in(['individual', 'business'])],
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'father_name' => ['required', 'string', 'min:2', 'max:100'],
            // تذکره‌ی الکترونیکی: 1400-0101-12345 ؛ تذکره‌ی کاغذی: فقط عدد
            'tazkira_number' => ['required', 'string', 'max:30', 'regex:/^\d[\d-]{3,28}\d$/', Rule::unique('sellers', 'tazkira_number')->ignore($sellerId)],
            'store_name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('sellers', 'store_name')->ignore($sellerId)],
            'business_name' => ['nullable', 'required_if:type,business', 'string', 'max:150'],
            'license_number' => ['nullable', 'required_if:type,business', 'string', 'max:50'],
            'is_local_producer' => ['boolean'],
            'is_social_seller' => ['boolean'],
            'social_url' => ['nullable', 'required_if:is_social_seller,true', 'url:http,https', 'max:255'],
            'has_brand' => ['boolean'],
            'brand_name' => ['nullable', 'required_if:has_brand,true', 'string', 'max:100'],
            'category_ids' => ['required', 'array', 'min:1', 'max:10'],
            'category_ids.*' => ['integer', Rule::exists(Category::class, 'id')->where('is_active', true)->whereNull('parent_id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tazkira_number.regex' => __('seller.validation.tazkira_format'),
            'tazkira_number.unique' => __('seller.validation.tazkira_taken'),
            'store_name.unique' => __('seller.validation.store_name_taken'),
            'category_ids.required' => __('seller.validation.categories_required'),
            'category_ids.min' => __('seller.validation.categories_required'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) __('seller.fields');
    }
}
