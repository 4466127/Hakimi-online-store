<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * یک ردیف دفتر حساب فروشنده: طلب از فروش (earning)، برگشت (reversal)، پرداخت/تسویه (payout)،
 * اصلاح دستی (adjustment). مبلغ مثبت = طلب فروشنده زیاد شد.
 *
 * مبلغ و مانده هیچ‌وقت ویرایش و ردیف هیچ‌وقت حذف نمی‌شود. فقط دو ستونِ «پیگیری تسویه» عوض می‌شوند
 * (و فقط از SellerLedger): `open_amount` (چقدر هنوز تسویه نشده) و `eligible_at` (از کی قابل تسویه است).
 *
 * وضعیت تسویه‌ی هر ردیف (از همین دو ستون حساب می‌شود، ذخیره نمی‌شود):
 *   pending            — هنوز قابل تسویه نیست (مثلاً پول هنوز دست پیک است)
 *   eligible           — قابل تسویه
 *   partially_settled  — بخشی تسویه شد
 *   settled            — کامل تسویه شد
 *
 * @property int $id
 * @property string|null $reference
 * @property int $seller_id
 * @property string $type
 * @property string $amount
 * @property string $balance_after
 * @property string $open_amount
 * @property Carbon|null $eligible_at
 */
class SellerTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['earning', 'reversal', 'payout', 'adjustment'];

    public const REFERENCE_PREFIX = 'ST-';

    public const REFERENCE_BASE = 100000;

    /** تنها ستون‌هایی که بعد از ثبت عوض می‌شوند. */
    private const MUTABLE = ['open_amount', 'eligible_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'open_amount' => 'decimal:2',
            'eligible_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SellerTransaction $tx): void {
            if (array_diff(array_keys($tx->getDirty()), self::MUTABLE) !== []) {
                throw new LogicException('Seller transactions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Seller transactions are immutable.'));
    }

    public static function referenceFor(int $id): string
    {
        return self::REFERENCE_PREFIX.(self::REFERENCE_BASE + $id);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function commissionEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SellerSettlementItem::class);
    }

    /** ردیف‌هایی که چیزی از آنها هنوز تسویه نشده. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('open_amount', '!=', 0);
    }

    /** ردیف‌های باز که موعد تسویه‌شان رسیده. */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->open()->whereNotNull('eligible_at')->where('eligible_at', '<=', now());
    }

    /** ردیف‌های باز که هنوز قابل تسویه نیستند. */
    public function scopePending(Builder $query): Builder
    {
        return $query->open()->where(fn (Builder $q) => $q->whereNull('eligible_at')->orWhere('eligible_at', '>', now()));
    }

    public function settlementStatus(): string
    {
        if (Money::isZero((string) $this->open_amount)) {
            return $this->type === 'payout' ? 'paid_out' : 'settled';
        }
        if (Money::compare((string) $this->open_amount, (string) $this->amount) !== 0) {
            return 'partially_settled';
        }

        return $this->eligible_at !== null && $this->eligible_at->lte(now()) ? 'eligible' : 'pending';
    }

    public function settlementStatusLabel(): string
    {
        return (string) __('commission.settlement_statuses.'.$this->settlementStatus());
    }

    public function typeLabel(): string
    {
        return (string) __('commission.transaction_types.'.$this->type);
    }
}
