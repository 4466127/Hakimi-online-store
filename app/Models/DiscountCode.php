<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * کد تخفیف (مرحله‌ی ۹-۳؛ از جیب بازارک، سهم فروشنده کم نمی‌شود).
 * در صفحه‌ها همیشه «کد تخفیف» نوشته می‌شود.
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property string $value
 * @property string|null $min_order_total
 * @property string|null $max_discount
 * @property int|null $max_uses
 * @property int|null $max_uses_per_phone
 * @property bool $first_order_only
 * @property int $used_count
 * @property bool $is_active
 * @property bool $show_on_site
 * @property array<string, string>|null $promo_title
 */
class DiscountCode extends Model
{
    use HasTranslations, LogsActivity;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED];

    /** used_count فقط از DiscountCodeService و زیر قفل عوض می‌شود. */
    protected $fillable = [
        'code', 'type', 'value', 'min_order_total', 'max_discount', 'max_uses', 'max_uses_per_phone',
        'first_order_only', 'starts_at', 'ends_at', 'is_active', 'show_on_site', 'promo_title', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'max_uses' => 'integer',
            'max_uses_per_phone' => 'integer',
            'first_order_only' => 'boolean',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'show_on_site' => 'boolean',
            'promo_title' => 'array',
        ];
    }

    /**
     * کد یکسان‌شده: عددهای فارسی ← لاتین، حروف بزرگ، بی فاصله؛ فقط A-Z، ۰-۹، «-» و «_».
     */
    public static function normaliseCode(?string $code): string
    {
        $code = mb_strtoupper(Num::toEn(trim((string) $code)));

        return substr((string) preg_replace('/[^A-Z0-9_-]/', '', $code), 0, 30);
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normaliseCode($value);
    }

    /**
     * کدی که در سایت نشان داده می‌شود (کارت صفحه‌ی اصلی و پیشنهاد صفحه‌ی تسویه): روشن، «نمایش در سایت»،
     * شروع‌شده، تمام‌نشده و سقفش پر نشده. اگر چند کد بود، تازه‌ترین تغییر.
     */
    public function scopeShownOnSite(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('show_on_site', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /** کد امروزِ سایت یا null. */
    public static function sitePromo(): ?self
    {
        return self::query()->shownOnSite()->first();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountCodeRedemption::class);
    }

    public function isPercent(): bool
    {
        return $this->type === self::TYPE_PERCENT;
    }

    public function activityLabel(): string
    {
        return $this->code;
    }
}
