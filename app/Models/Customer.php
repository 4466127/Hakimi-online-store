<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Phone;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * مشتری فروشگاه. با شماره‌ی تلفن و رمز وارد می‌شود (guard «customer»)، جدا از کاربران پنل.
 * هر مشتری می‌تواند با پر کردن فورم «در بازارک بفروشید» فروشنده هم بشود (Seller).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $phone
 * @property bool $is_active
 */
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'password', 'is_active', 'notes', 'province_id', 'city', 'address'];

    protected $hidden = ['password', 'remember_token'];

    protected array $activityHidden = ['password', 'remember_token', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_guest' => 'boolean',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'orders_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            $customer->uuid ??= (string) Str::uuid();
        });
    }

    /** شماره همیشه به شکل استاندارد 07XXXXXXXX ذخیره می‌شود. */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Phone::normalize($value) ?? $value,
        );
    }

    /** ایمیل خالی به شکل NULL ذخیره شود (ستون یکتاست). */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => ($value = trim((string) $value)) === '' ? null : mb_strtolower($value),
        );
    }

    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** دفترچه‌ی آدرس (آخرین استفاده‌شده اول). */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('last_used_at')->orderByDesc('id');
    }

    public function activityLabel(): string
    {
        return $this->name.' ('.$this->phone.')';
    }
}
