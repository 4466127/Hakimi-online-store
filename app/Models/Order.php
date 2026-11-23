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
 * @property string|null $delivery_code
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

    /** شکل قدیمی شماره (سفارش‌های پیش از ۲ قوس ۱۴۰۵): HS-000123. */
    public const NUMBER_PREFIX = 'HS-';

    /**
     * شماره‌ی سفارش **فقط عدد** است (خواست صاحب پروژه، ۲ قوس ۱۴۰۵؛ مثل دیجی‌کالا): پایه + شناسه،
     * یعنی ۹ رقم از ۱۰۰۰۰۰۰۰۱ به بالا. گفتن و تایپ کردنش در تلفن آسان است.
     */
    public const NUMBER_BASE = 100000000;

    /** وضعیت، پرداخت، شماره و جمع‌های پرداخت‌شده را فقط سرویس‌ها (forceFill) می‌گذارند. */
    protected $guarded = ['id', 'uuid', 'number', 'delivery_code', 'status', 'payment_status', 'paid_total', 'refunded_total'];

    protected function casts(): array
    {
        return [
            'is_gift' => 'boolean',
            'delivery_date' => 'date',
            'delivery_weight_grams' => 'integer',
            'delivery_lat' => 'decimal:7',
            'delivery_lng' => 'decimal:7',
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

    /** شماره‌ی عددی سفارش از شناسه: ۱۰۰۰۰۰۰۰۱، ۱۰۰۰۰۰۰۰۲، … */
    public static function numberFor(int $id): string
    {
        return (string) (self::NUMBER_BASE + $id);
    }

    /** کد تحویل: پنج رقم تصادفی که مشتری هنگام گرفتن جنس به پیک یا نمایندگی می‌گوید. */
    public static function generateDeliveryCode(): string
    {
        return (string) random_int(10000, 99999);
    }

    /**
     * شماره‌هایی که مشتریِ تایپ‌کننده می‌تواند منظورش باشد (برای پیگیری).
     * ارقام فارسی، فاصله و خط تیره قبول است؛ هم شماره‌ی عددی تازه و هم شکل قدیمی «HS-000123».
     *
     * @return list<string>
     */
    public static function numberCandidates(?string $input): array
    {
        $digits = array_merge(
            array_combine(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], range(0, 9)),
            array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)),
        );
        $text = strtoupper(trim(strtr((string) $input, array_map('strval', $digits))));
        if (preg_match('/^(?:HS)?\s*-?\s*0*(\d{1,12})$/', $text, $m) !== 1) {
            return [];
        }

        $value = (int) $m[1];
        $candidates = [];
        if ($value >= self::NUMBER_BASE) {
            $candidates[] = (string) $value;                // شماره‌ی عددی تازه
        } else {
            $candidates[] = self::numberFor($value);        // کسی که فقط شناسه را تایپ کرده
        }
        if ($value <= 999999) {
            $candidates[] = self::NUMBER_PREFIX.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($candidates));
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

    /** موقعیت روی نقشه ثبت شده؟ (فقط درِ خانه) */
    public function hasLocation(): bool
    {
        return ! $this->isPickup() && $this->delivery_lat !== null && $this->delivery_lng !== null;
    }

    /** «lat,lng» با ۶ رقم اعشار، یا null */
    public function locationText(): ?string
    {
        return $this->hasLocation()
            ? rtrim(rtrim((string) $this->delivery_lat, '0'), '.').','.rtrim(rtrim((string) $this->delivery_lng, '0'), '.')
            : null;
    }

    /** دکمه‌ی «مسیریابی»: گوگل مپ از جای فعلی پیک تا موقعیت مشتری. */
    public function directionsUrl(): ?string
    {
        $point = $this->locationText();

        return $point === null ? null : 'https://www.google.com/maps/dir/?api=1&destination='.$point;
    }

    /** دیدن نقطه روی گوگل مپ. */
    public function mapUrl(): ?string
    {
        $point = $this->locationText();

        return $point === null ? null : 'https://www.google.com/maps/search/?api=1&query='.$point;
    }

    /** هنوز می‌شود لغو کرد؟ (تا قبل از تحویل و لغو) */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_SHIPPED], true);
    }
}
