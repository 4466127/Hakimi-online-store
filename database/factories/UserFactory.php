<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * کاربر پنل برای تست‌ها. رمز همه: «password1».
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '07'.fake()->unique()->numerify('########'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password1'),
            'role' => UserRole::Staff,
            'is_active' => true,
            'password_changed_at' => now(), // رمز خودش را گذاشته؛ مجبور به تغییر نیست
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function owner(): static
    {
        return $this->role(UserRole::Owner);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** کاربری که هنوز رمز اولیه دارد و باید آن را عوض کند. */
    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['password_changed_at' => null]);
    }
}
