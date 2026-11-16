<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use App\Support\Phone;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * کاربر پنل مدیریت (عضو خانواده). با شماره‌ی تلفن وارد می‌شود.
 * مشتریان فروشگاه مدل جدا دارند (Customer، مرحله‌ی ۴).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $phone
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** ستون‌هایی که در تاریخچه‌ی فعالیت نوشته نمی‌شود. */
    protected array $activityHidden = ['password', 'remember_token', 'failed_logins', 'locked_until', 'last_login_at', 'password_changed_at'];

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

    public function activityLabel(): string
    {
        return $this->name;
    }
}
