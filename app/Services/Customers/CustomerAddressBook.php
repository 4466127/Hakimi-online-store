<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Services\Delivery\DeliveryCalculator;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دفترچه‌ی آدرس مشتری عضو (۶ قوس ۱۴۰۵).
 *
 * - بعد از هر سفارش «درِ خانه» آدرس همان سفارش نگه داشته می‌شود (اگر مشتری تیک «نگه دار» را برنداشته باشد).
 * - آدرس تکراری دوباره ساخته نمی‌شود: همان ولایت + همان متن آدرس + همان شماره‌ی گیرنده ← فقط به‌روز می‌شود.
 * - حداکثر MAX آدرس؛ آدرس تازه‌ی یازدهم جای آدرسی را می‌گیرد که از همه دیرتر استفاده شده.
 * - مهمان (بی ورود) دفترچه ندارد.
 */
class CustomerAddressBook
{
    public const MAX = 10;

    /** @return Collection<int, CustomerAddress> آدرس‌های مشتری؛ آخرین استفاده‌شده اول. */
    public function list(Customer $customer): Collection
    {
        return CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->with('province')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }

    /** آدرس سفارش را در دفترچه‌ی صاحب سفارش نگه دار (یا اگر بود، به‌روز کن). */
    public function rememberFromOrder(Order $order): ?CustomerAddress
    {
        if ($order->customer_id === null
            || $order->delivery_method !== DeliveryCalculator::METHOD_DELIVERY
            || trim((string) $order->address_line) === '') {
            return null;
        }

        return DB::transaction(function () use ($order): CustomerAddress {
            $customerId = (int) $order->customer_id;
            // قفل روی ردیف مشتری: دو سفارش همزمان یک آدرس را دو بار نسازند
            Customer::query()->whereKey($customerId)->lockForUpdate()->first();

            $fields = [
                'recipient_name' => $order->recipient_name,
                'recipient_phone' => $order->recipient_phone,
                'province_id' => $order->province_id,
                'city' => $order->city,
                'district' => $order->district,
                'address_line' => trim((string) $order->address_line),
                'delivery_instructions' => $order->delivery_instructions,
                'last_used_at' => now(),
            ];
            // موقعیت نقشه فقط اگر این بار گذاشته شده (نبودنش موقعیت قبلی را پاک نکند)
            if ($order->delivery_lat !== null && $order->delivery_lng !== null) {
                $fields['delivery_lat'] = $order->delivery_lat;
                $fields['delivery_lng'] = $order->delivery_lng;
                $fields['delivery_location_source'] = $order->delivery_location_source;
            }

            $existing = $this->findSame($customerId, $order);
            if ($existing !== null) {
                $existing->fill($fields)->save();

                return $existing;
            }

            $this->makeRoom($customerId);

            $address = new CustomerAddress($fields);
            $address->customer_id = $customerId;
            $address->save();

            return $address;
        });
    }

    /** آدرس را پاک کن؛ فقط اگر مال همین مشتری باشد. */
    public function delete(Customer $customer, int $addressId): bool
    {
        return CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->whereKey($addressId)
            ->delete() > 0;
    }

    private function findSame(int $customerId, Order $order): ?CustomerAddress
    {
        $key = self::normalize((string) $order->address_line);
        $phone = Phone::normalize($order->recipient_phone) ?? $order->recipient_phone;

        return CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->where('province_id', $order->province_id)
            ->where('recipient_phone', $phone)
            ->get()
            ->first(fn (CustomerAddress $a) => self::normalize($a->address_line) === $key);
    }

    /** اگر دفترچه پر است، آدرسی که از همه دیرتر استفاده شده برداشته می‌شود. */
    private function makeRoom(int $customerId): void
    {
        $ids = CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() >= self::MAX) {
            CustomerAddress::query()->whereIn('id', $ids->slice(self::MAX - 1)->all())->delete();
        }
    }

    /** برای مقایسه: فاصله‌های اضافه، «ی/ک» عربی و نیم‌فاصله یکی می‌شود. */
    private static function normalize(string $text): string
    {
        $text = str_replace(['ي', 'ك', "\u{200C}"], ['ی', 'ک', ' '], $text);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
