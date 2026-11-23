<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * سفارش والد: یک تسویه و یک شماره برای مشتری. هر فروشنده یک «سفارش فروشنده» (SellerOrder) دارد.
 *
 * - خریدار (purchaser_*) و گیرنده (recipient_*) دو نفر جدا می‌توانند باشند.
 * - قیمت، نام اجناس و کرایه هنگام سفارش کاپی شده‌اند.
 * - مهمان: customer_id خالی؛ پیگیری با «شماره‌ی سفارش + تلفن».
 * - آدرس عمومی صفحه‌ی سفارش با uuid است (حدس‌زدنی نیست).
 * - وضعیت و پرداخت و جمع‌های مالی را فقط سرویس‌ها عوض می‌کنند (OrderStateMachine، CheckoutService،
 *   سرویس‌های پرداخت)، نه فورم یا درخواست.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $number
 * @property int|null $customer_id
 * @property string $status
 * @property string $payment_status
 * @property string $payment_method_code
 * @property string $subtotal
 * @property string $discount_total
 * @property string $delivery_fee
 * @property string $payment_fee
 * @property string $total
 * @property string $paid_total
 * @property string $delivery_method
 * @property string $delivery_mode
 * @property string $purchaser_name
 * @property string $purchaser_phone
 * @property string $recipient_name
 * @property string $recipient_phone
 */
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURNED = 'returned';

    /** @var list<string> */
    public const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];

    /** رنگ نشان وضعیت (Filament و صفحه‌ی مشتری). */
    public const STATUS_COLORS = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'returned' => 'gray',
    ];

    /** @var list<string> وضعیت پرداخت */
    public const PAYMENT_STATUSES = ['unpaid', 'awaiting_collection', 'pending', 'partially_paid', 'paid', 'failed', 'partially_refunded', 'refunded'];

    public const NUMBER_PREFIX = 'HS-';

    /** وضعیت، پرداخت، شماره و جمع‌های پرداخت‌شده را فقط سرویس‌ها (forceFill) می‌گذارند. */
    protected $guarded = ['id', 'uuid', 'number', 'status', 'payment_status', 'paid_total', 'refunded_total'];

    protected function casts(): array
    {
        return [
            'is_gift' => 'boolean',
            'delivery_date' => 'date',
            'delivery_weight_grams' => 'integer',
            'needs_stock_check' => 'boolean',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    /** HS-000123 از شناسه‌ی سفارش. */
    public static function numberFor(int $id): string
    {
        return self::NUMBER_PREFIX.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** شماره‌ی تایپ‌شده‌ی مشتری را به شکل استاندارد درمی‌آورد («hs-123» ← «HS-000123»)؛ null اگر شکلش درست نیست. */
    public static function normalizeNumber(?string $input): ?string
    {
        $digits = array_merge(
            array_combine(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9)),
            array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)),
        );
        $input = strtoupper(trim(strtr((string) $input, array_map('strval', $digits))));
        if (preg_match('/^(?:HS)?\s*-?\s*0*(\d{1,9})$/', $input, $m) !== 1) {
            return null;
        }

        return self::numberFor((int) $m[1]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(CollectionPoint::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class)->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** پرداخت فعلی (آخرین تلاش). */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function isPickup(): bool
    {
        return $this->delivery_method === 'pickup';
    }

    /** هنوز می‌شود لغو کرد؟ (تا قبل از تحویل و لغو) */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_SHIPPED], true);
    }
}
