<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\CollectionPoint;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Province;
use App\Services\Cart\CartService;
use App\Services\Delivery\DeliveryCalculator;
use App\Services\Delivery\DeliveryOption;
use App\Services\StoreInfo;
use Illuminate\Support\Collection;

/**
 * داده‌ی آماده‌ی صفحه‌ی تسویه (مرحله‌ی ۲-۲).
 *
 * همه‌ی عددها و گزینه‌ها در سرور حساب می‌شود:
 *   - خط‌های سبد و جمع‌ها (قیمت زنده)،
 *   - روزهای تحویل و کرایه‌ی هر روز، برای **هر ولایتِ تحویل‌دار** (تا عوض کردن ولایت در فورم،
 *     بی بارگذاری دوباره‌ی صفحه، روزهای همان ولایت را نشان بدهد)،
 *   - نمایندگی‌های تحویل حضوری،
 *   - روش‌های پرداخت نقدِ فعال و اینکه هر کدام با کدام روش تحویل می‌خواند.
 *
 * ثبت سفارش (CheckoutService) همه‌ی این‌ها را زیر قفل دوباره حساب می‌کند؛ این‌جا فقط برای نمایش است.
 */
class CheckoutPage
{
    public function __construct(
        private readonly CartService $carts,
        private readonly DeliveryCalculator $delivery,
        private readonly StoreInfo $store,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Cart $cart, ?Customer $customer): array
    {
        $lines = $this->carts->lines($cart);
        $totals = $this->carts->totals($lines);
        $provinces = Province::query()->deliverable()->get();
        $points = CollectionPoint::query()->active()->get();
        $pickupEnabled = $this->store->pickupEnabled() && $points->isNotEmpty();

        $days = [];
        $tooHeavy = [];
        foreach ($provinces as $province) {
            $zone = $this->delivery->zoneFor($province);
            if ($zone === null) {
                $days[$province->id] = [];

                continue;
            }
            $tooHeavy[$province->id] = $this->delivery->isTooHeavy($zone, $totals['weight']);
            $days[$province->id] = $tooHeavy[$province->id] ? [] : $this->delivery->options($zone, $totals['weight']);
        }

        $deliveryPossible = $provinces->isNotEmpty() && collect($days)->contains(fn (array $options) => $options !== []);

        return [
            'lines' => $lines,
            'totals' => $totals,
            'provinces' => $provinces,
            'days' => $days,
            'tooHeavy' => $tooHeavy,
            'deliveryPossible' => $deliveryPossible,
            'pickupEnabled' => $pickupEnabled,
            'points' => $points,
            'methods' => $this->paymentMethods(),
            'defaults' => $this->defaults($customer, $deliveryPossible, $pickupEnabled, $provinces, $days),
        ];
    }

    /**
     * روش‌های پرداخت نقدِ فعال، با اینکه هر کدام برای کدام روش تحویل است.
     * درگاه آنلاین (حساب‌پی) تا مرحله‌ی ۵ در سفارش‌دادن نمی‌آید.
     *
     * @return Collection<int, array{method: PaymentMethod, delivery: bool, pickup: bool}>
     */
    public function paymentMethods(): Collection
    {
        return PaymentMethod::query()->active()->orderBy('position')->orderBy('id')->get()
            ->filter(fn (PaymentMethod $method) => $method->isCash())
            ->map(fn (PaymentMethod $method) => [
                'method' => $method,
                'delivery' => $method->fitsDelivery(DeliveryCalculator::METHOD_DELIVERY),
                'pickup' => $method->fitsDelivery(DeliveryCalculator::METHOD_PICKUP),
            ])
            ->values();
    }

    /**
     * مقدارهای پیش‌فرض فورم: معلومات مشتری عضو، روش تحویل ممکن، ولایت و زودترین روز.
     *
     * @param  Collection<int, Province>  $provinces
     * @param  array<int, list<DeliveryOption>>  $days
     * @return array<string, mixed>
     */
    private function defaults(?Customer $customer, bool $deliveryPossible, bool $pickupEnabled, Collection $provinces, array $days): array
    {
        $method = $deliveryPossible || ! $pickupEnabled
            ? DeliveryCalculator::METHOD_DELIVERY
            : DeliveryCalculator::METHOD_PICKUP;

        $province = $provinces->first(fn (Province $p) => ($days[$p->id] ?? []) !== []) ?? $provinces->first();
        $firstDay = $province === null ? null : ($days[$province->id][0] ?? null);

        return [
            'purchaser_name' => $customer?->name,
            'purchaser_phone' => $customer?->phone,
            'purchaser_email' => $customer?->email,
            'delivery_method' => $method,
            'province_id' => $province?->id,
            'delivery_date' => $firstDay?->dateString(),
        ];
    }
}
