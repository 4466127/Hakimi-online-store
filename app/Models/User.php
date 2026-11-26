<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use App\Support\Phone;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * کاربر پنل مدیریت (عضو خانواده). با شماره‌ی تلفن وارد می‌شود.
 * مشتریان فروشگاه مدل جدا دارند (Customer، مرحله‌ی ۴).
 * ورود دومرحله‌ای (اختیاری، از «حساب من»): کد ۶ رقمی برنامه‌ی Google Authenticator و مانند آن.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $phone
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'role', 'is_active'];

    /** مدلی که تازه ساخته شده هم این دو ستون را داشته باشد (Filament در صفحه‌ی «حساب من» آن‌ها را می‌خواند). */
    protected $attributes = [
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
    ];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /** ستون‌هایی که در تاریخچه‌ی فعالیت نوشته نمی‌شود. */
    protected array $activityHidden = ['password', 'remember_token', 'failed_logins', 'locked_until', 'last_login_at', 'password_changed_at', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'failed_logins' => 'integer',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            // رمزنگاری با APP_KEY (اگر دیتابیس دزدیده شود کلید خوانده نمی‌شود)
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });

        static::saving(function (User $user): void {
            // رمز عوض شد: اگر خود کاربر عوض کرد «عوض‌شده» حساب می‌شود؛
            // اگر مدیر دیگری رمز داد، کاربر در اولین ورود باید رمز خودش را بگذارد.
            if ($user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
                $byHimself = $user->exists && auth()->id() === $user->getKey();
                $user->password_changed_at = $byHimself ? now() : null;
            }
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
            set: fn (?string $value) => ($value === null || trim($value) === '') ? null : mb_strtolower(trim($value)),
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function mustChangePassword(): bool
    {
        return $this->password_changed_at === null;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    // ورود دومرحله‌ای (Filament)؛ مقدارها فقط از همین راه ذخیره می‌شوند و در $fillable نیستند

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        // در برنامه‌ی کد زیر نام فروشگاه نشان داده می‌شود؛ شماره بهتر از نام است چون یکتاست
        return $this->phone;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
