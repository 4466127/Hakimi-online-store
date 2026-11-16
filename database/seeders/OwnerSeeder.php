<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * اولین کاربر پنل (صاحب فروشگاه). فقط وقتی ساخته می‌شود که هیچ صاحبِ فروشگاهی نباشد.
 *
 * شماره و رمز اولیه از .env می‌آید (STORE_OWNER_PHONE و STORE_OWNER_PASSWORD).
 * اگر رمز در .env نبود، یک رمز تصادفی ساخته و فقط همین یک بار در ترمینال نشان داده می‌شود.
 * در اولین ورود، سیستم می‌خواهد که رمز عوض شود.
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('role', UserRole::Owner->value)->exists()) {
            return;
        }

        $config = config('store.initial_owner');
        $password = $config['password'] ?: Str::password(12, symbols: false);

        $owner = new User([
            'name' => $config['name'],
            'phone' => $config['phone'],
            'role' => UserRole::Owner,
            'is_active' => true,
            'password' => $password,
        ]);
        $owner->save();

        $this->command?->newLine();
        $this->command?->info('کاربر صاحب فروشگاه ساخته شد / Owner account created:');
        $this->command?->line('  phone:    '.$owner->phone);
        $this->command?->line('  password: '.($config['password'] ? '(from .env STORE_OWNER_PASSWORD)' : $password));
        $this->command?->warn('  این رمز فقط همین یک بار نشان داده می‌شود. در اولین ورود باید عوض شود.');
        $this->command?->newLine();
    }
}
