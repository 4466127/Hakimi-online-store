<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * یک پیام سرور-به-سرور درگاه (حساب‌پی) و نتیجه‌ی پردازشش. هر پیام فقط یک بار (provider + event_id).
 * دفتر است: ویرایش نمی‌شود، جز علامت «بررسی شد» مدیر.
 *
 * @property string $provider
 * @property string $event_id
 * @property string $event_type
 * @property int|null $payment_id
 * @property string|null $reference
 * @property string|null $transaction_id
 * @property string|null $amount
 * @property string $outcome
 * @property bool $needs_review
 * @property Carbon|null $reviewed_at
 * @property array<string, mixed>|null $payload
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    /** پول رسید و پرداخت «پرداخت‌شده» شد. */
    public const PAID = 'paid';

    /** همان پیام دوباره رسید (اثری نداشت). */
    public const DUPLICATE = 'duplicate';

    /** مبلغ پیام با مبلغ سفارش نمی‌خواند — پرداخت‌شده نشد. */
    public const AMOUNT_MISMATCH = 'amount_mismatch';

    /** پرداختی با این مرجع پیدا نشد. */
    public const UNKNOWN_PAYMENT = 'unknown_payment';

    /** پول برای پرداختی آمد که لغو یا عوض شده بود (باید پس داده یا سفارش برگردانده شود). */
    public const PAID_ON_CLOSED = 'paid_on_closed';

    /** این پرداخت قبلاً با تراکنش دیگری پرداخت شده بود (دو بار پول آمده). */
    public const DOUBLE_PAYMENT = 'double_payment';

    /** رد پرداخت ثبت شد؛ سفارش منتظر می‌ماند و مشتری می‌تواند دوباره بپردازد. */
    public const FAILURE_RECORDED = 'failure_recorded';

    /** رد پرداختی که دیگر منتظر نبود (اثری ندارد؛ پرداخت‌شده هرگز پایین نمی‌آید). */
    public const IGNORED = 'ignored';

    public const OUTCOMES = [
        self::PAID, self::DUPLICATE, self::AMOUNT_MISMATCH, self::UNKNOWN_PAYMENT,
        self::PAID_ON_CLOSED, self::DOUBLE_PAYMENT, self::FAILURE_RECORDED, self::IGNORED,
    ];

    protected $fillable = [
        'provider', 'event_id', 'event_type', 'payment_id', 'reference', 'transaction_id',
        'amount', 'outcome', 'needs_review', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentEvent $event): void {
            if (array_diff(array_keys($event->getDirty()), ['reviewed_by', 'reviewed_at', 'needs_review']) !== []) {
                throw new LogicException('Payment events are a ledger and cannot be edited.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payment events cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('needs_review', true)->whereNull('reviewed_at');
    }
}
