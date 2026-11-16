<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\Concerns\LogsActivity;
use App\Support\Num;
use App\Support\Phone;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * فروشنده‌ی بازارک. ثبت‌نام چهار قدم دارد (مثل دیجی‌کالا):
 *   ۱. معلومات (نوع فروشنده و معلومات تکمیلی)   ۲. آدرس   ۳. قوانین   ۴. بررسی و ارسال
 * بعد از ارسال، مدیر از پنل تأیید یا رد می‌کند.
 *
 * @property int $id
 * @property int $customer_id
 * @property SellerStatus $status
 * @property SellerType|null $type
 * @property int $onboarding_step
 * @property string|null $store_name
 * @property list<int>|null $category_ids
 */
class Seller extends Model
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory, LogsActivity;

    /** قدم‌های ثبت‌نام: شماره ← کلید متن. */
    public const STEPS = [1 => 'info', 2 => 'address', 3 => 'rules', 4 => 'review'];

    /** مقدار پیش‌فرض (همان پیش‌فرض دیتابیس، تا مدلِ تازه‌ساخته هم داشته باشد). */
    protected $attributes = [
        'status' => 'draft',
        'onboarding_step' => 1,
        'is_local_producer' => false,
        'is_social_seller' => false,
        'has_brand' => false,
    ];

    protected $fillable = [
        'type', 'full_name', 'father_name', 'tazkira_number', 'store_name', 'business_name', 'license_number',
        'is_local_producer', 'is_social_seller', 'social_url', 'has_brand', 'brand_name', 'category_ids',
        'province_id', 'city', 'address', 'phone', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellerStatus::class,
            'type' => SellerType::class,
            'onboarding_step' => 'integer',
            'is_local_producer' => 'boolean',
            'is_social_seller' => 'boolean',
            'has_brand' => 'boolean',
            'category_ids' => 'array',
            'terms_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Seller $seller): void {
            $seller->uuid ??= (string) Str::uuid();
            $seller->status ??= SellerStatus::Draft;
        });
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => ($value = trim((string) $value)) === '' ? null : (Phone::normalize($value) ?? $value),
        );
    }

    /** نمبر تذکره بی فاصله و با ارقام انگلیسی ذخیره می‌شود (برای یکتا بودن). */
    protected function tazkiraNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => self::normalizeTazkira($value),
        );
    }

    public static function normalizeTazkira(?string $value): ?string
    {
        $value = preg_replace('/[\s\x{200C}]+/u', '', Num::toEn(trim((string) $value)));
        $value = str_replace(['_', '/', '—', '–'], '-', (string) $value);

        return $value === '' ? null : $value;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** آیا فروشنده اجازه دارد این قدم را باز کند؟ (قدم‌های بعدی تا تمام شدن قبلی قفل است.) */
    public function canOpenStep(int $step): bool
    {
        return $this->status->isEditable() && $step >= 1 && $step <= $this->onboarding_step && isset(self::STEPS[$step]);
    }

    /** قدم تمام شد؛ قدم بعدی باز شود. */
    public function completeStep(int $step): void
    {
        $this->onboarding_step = max($this->onboarding_step, min($step + 1, count(self::STEPS)));
    }

    /**
     * دسته‌های اصلی که فروشنده انتخاب کرده.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        $ids = array_map('intval', $this->category_ids ?? []);

        return $ids === [] ? collect() : Category::query()->whereIn('id', $ids)->orderBy('position')->get();
    }

    public function activityLabel(): string
    {
        return (string) ($this->store_name ?: $this->full_name ?: '#'.$this->id);
    }
}
