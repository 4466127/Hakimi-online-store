<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Http\Controllers\Storefront\CheckoutController;
use App\Rules\AfghanMobile;
use App\Services\Delivery\DeliveryCalculator;
use App\Services\Marketing\CampaignAttribution;
use App\Services\Orders\CheckoutData;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * اعتبارسنجی فورم تسویه (مرحله‌ی ۲-۲).
 *
 * اینجا فقط «شکل» ورودی بررسی می‌شود؛ هیچ مبلغی از مرورگر گرفته نمی‌شود و قاعده‌های اصلی
 * (موجودی، روز تحویل، کرایه، سازگاری روش پرداخت با تحویل) در `CheckoutService` زیر قفل
 * دوباره بررسی می‌شود.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** شماره‌ها استاندارد می‌شود؛ اگر گیرنده خود خریدار است، نام و شماره‌اش کاپی می‌شود. */
    protected function prepareForValidation(): void
    {
        $purchaserPhone = Phone::normalize((string) $this->input('purchaser_phone')) ?? $this->input('purchaser_phone');
        $isGift = $this->boolean('is_gift');

        // روز تحویل حضوری خانه‌ی جدا دارد (`pickup_date`)؛ در سرور همان «روز تحویل» سفارش است
        if ($this->input('delivery_method') === DeliveryCalculator::METHOD_PICKUP && $this->filled('pickup_date')) {
            $this->merge(['delivery_date' => $this->input('pickup_date')]);
        }

        $this->merge([
            'purchaser_name' => trim((string) $this->input('purchaser_name')),
            'purchaser_phone' => $purchaserPhone,
            'is_gift' => $isGift,
            'recipient_name' => $isGift ? trim((string) $this->input('recipient_name')) : trim((string) $this->input('purchaser_name')),
            'recipient_phone' => $isGift
                ? (Phone::normalize((string) $this->input('recipient_phone')) ?? $this->input('recipient_phone'))
                : $purchaserPhone,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isDelivery = $this->input('delivery_method') === DeliveryCalculator::METHOD_DELIVERY;

        return [
            // خریدار
            'purchaser_name' => ['required', 'string', 'min:2', 'max:100'],
            'purchaser_phone' => ['required', 'string', 'max:20', new AfghanMobile],
            'purchaser_email' => ['nullable', 'email', 'max:150'],
            'is_gift' => ['boolean'],

            // گیرنده (اگر خود خریدار است، در prepareForValidation کاپی شده)
            'recipient_name' => ['required', 'string', 'min:2', 'max:100'],
            'recipient_phone' => ['required', 'string', 'max:20', new AfghanMobile],

            // تحویل
            'delivery_method' => ['required', Rule::in([DeliveryCalculator::METHOD_DELIVERY, DeliveryCalculator::METHOD_PICKUP])],
            'province_id' => [$isDelivery ? 'required' : 'nullable', 'integer', Rule::exists('provinces', 'id')->where('is_active', true)->where('delivery_enabled', true)],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'address_line' => [$isDelivery ? 'required' : 'nullable', 'string', 'min:5', 'max:500'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            // روز برای هر دو روش لازم است (حضوری هم روز گرفتن جنس را می‌خواهد)
            'delivery_date' => ['required', 'date_format:Y-m-d'],
            // خالی = اولین نمایندگی فعال (سرویس خودش انتخاب می‌کند)
            'collection_point_id' => ['nullable', 'integer', Rule::exists('collection_points', 'id')->where('is_active', true)],
            // موقعیت روی نقشه: اختیاری؛ هر دو با هم و داخل افغانستان
            'delivery_lat' => ['nullable', 'numeric', 'required_with:delivery_lng', 'between:'.implode(',', config('store.maps.bounds.lat'))],
            'delivery_lng' => ['nullable', 'numeric', 'required_with:delivery_lat', 'between:'.implode(',', config('store.maps.bounds.lng'))],
            'delivery_location_source' => ['nullable', Rule::in(['map', 'gps'])],

            // پرداخت و یادداشت
            'payment_method_code' => ['required', 'string', Rule::exists('payment_methods', 'code')->where('is_active', true)],
            'pickup_date' => ['nullable', 'date_format:Y-m-d'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            // «این آدرس را برای خرید بعدی نگه دار» (فقط مشتری عضو؛ نبودنش = بلی)
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'address_line.required' => __('checkout.errors.address_required'),
            'province_id.required' => __('checkout.errors.address_required'),
            'province_id.exists' => __('checkout.errors.delivery_unavailable'),
            'delivery_date.required' => __('checkout.errors.delivery_date_invalid'),
            'delivery_date.date_format' => __('checkout.errors.delivery_date_invalid'),
            'collection_point_id.exists' => __('checkout.errors.collection_point_invalid'),
            'payment_method_code.exists' => __('checkout.errors.payment_unavailable'),
            'delivery_lat.*' => __('checkout.location.invalid'),
            'delivery_lng.*' => __('checkout.location.invalid'),
            'delivery_location_source.*' => __('checkout.location.invalid'),
        ];
    }

    /** داده‌ی تسویه برای سرویس (بی هیچ مبلغی). */
    public function toCheckoutData(): CheckoutData
    {
        $isDelivery = $this->string('delivery_method')->toString() === DeliveryCalculator::METHOD_DELIVERY;
        // برچسب تبلیغ فقط از کوکی رمزشده‌ی خود ما، نه از فورم
        $campaign = app(CampaignAttribution::class)->current($this);

        return new CheckoutData(
            purchaserName: $this->string('purchaser_name')->toString(),
            purchaserPhone: $this->string('purchaser_phone')->toString(),
            recipientName: $this->string('recipient_name')->toString(),
            recipientPhone: $this->string('recipient_phone')->toString(),
            deliveryMethod: $this->string('delivery_method')->toString(),
            paymentMethodCode: $this->string('payment_method_code')->toString(),
            purchaserEmail: $this->filled('purchaser_email') ? $this->string('purchaser_email')->toString() : null,
            isGift: $this->boolean('is_gift'),
            provinceId: $isDelivery ? $this->integer('province_id') : null,
            city: $isDelivery ? $this->cleaned('city') : null,
            district: $isDelivery ? $this->cleaned('district') : null,
            addressLine: $isDelivery ? $this->cleaned('address_line') : null,
            deliveryLat: $isDelivery && $this->filled('delivery_lat') && $this->filled('delivery_lng') ? (string) $this->input('delivery_lat') : null,
            deliveryLng: $isDelivery && $this->filled('delivery_lat') && $this->filled('delivery_lng') ? (string) $this->input('delivery_lng') : null,
            deliveryLocationSource: $isDelivery ? $this->cleaned('delivery_location_source') : null,
            deliveryInstructions: $isDelivery ? $this->cleaned('delivery_instructions') : null,
            deliveryDate: $this->string('delivery_date')->toString(),
            collectionPointId: $isDelivery ? null : ($this->filled('collection_point_id') ? $this->integer('collection_point_id') : null),
            customerNote: $this->cleaned('customer_note'),
            customerId: $this->user('customer')?->id,
            source: 'web',
            utmSource: $campaign['source'] ?? null,
            utmMedium: $campaign['medium'] ?? null,
            utmCampaign: $campaign['campaign'] ?? null,
            // کد تخفیف فقط همانی که در صفحه‌ی تسویه «اعمال» شد (نشست)، نه خانه‌ی فورم
            discountCode: $this->session()->get(CheckoutController::DISCOUNT_SESSION_KEY),
            locale: app()->getLocale(),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }

    /** متن کوتاه‌شده و پاک، یا null اگر خالی بود. */
    private function cleaned(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
