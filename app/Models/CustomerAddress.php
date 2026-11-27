<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک آدرس ذخیره‌شده‌ی مشتری (دفترچه‌ی آدرس). فقط `CustomerAddressBook` آن را می‌سازد و به‌روز می‌کند.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $recipient_name
 * @property string $recipient_phone
 * @property int|null $province_id
 * @property string|null $city
 * @property string|null $district
 * @property string $address_line
 * @property string|null $delivery_instructions
 * @property string|null $delivery_lat
 * @property string|null $delivery_lng
 * @property string|null $delivery_location_source
 */
class CustomerAddress extends Model
{
    protected $fillable = ['recipient_name', 'recipient_phone', 'province_id', 'city', 'district', 'address_line',
        'delivery_instructions', 'delivery_lat', 'delivery_lng', 'delivery_location_source', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'delivery_lat' => 'decimal:7',
            'delivery_lng' => 'decimal:7',
            'last_used_at' => 'datetime',
        ];
    }

    protected function recipientPhone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Phone::normalize($value) ?? $value);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** یک خط کوتاه برای نمایش: «کابل، کارته‌ی چهار — سرک اول، خانه‌ی سبز». */
    public function summary(): string
    {
        $place = collect([$this->province?->tr('name'), $this->city, $this->district])
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')
            ->unique()
            ->implode('، ');

        return trim($place.($place !== '' ? ' — ' : '').$this->address_line);
    }

    /** داده‌ای که صفحه‌ی تسویه برای پر کردن فورم لازم دارد (فقط همین آدرس، بی شناسه‌ی مشتری). */
    public function toCheckoutFields(): array
    {
        return [
            'id' => $this->id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'province_id' => $this->province_id === null ? '' : (string) $this->province_id,
            'city' => (string) $this->city,
            'district' => (string) $this->district,
            'address_line' => $this->address_line,
            'delivery_instructions' => (string) $this->delivery_instructions,
            'delivery_lat' => $this->delivery_lat === null ? '' : (string) $this->delivery_lat,
            'delivery_lng' => $this->delivery_lng === null ? '' : (string) $this->delivery_lng,
            'delivery_location_source' => (string) $this->delivery_location_source,
        ];
    }
}
