<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * تنظیمات فروشگاه که مدیر از پنل عوض می‌کند (نام، تلفن، واتساپ، آدرس، لوگو…).
 *
 * همه‌ی تنظیمات یک‌جا خوانده و cache می‌شود تا هر صفحه فقط یک بار (یا هیچ بار) به دیتابیس برود.
 * مقدار پیش‌فرض هر کلید در config/store.php بخش defaults است.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? config('store.defaults')[$key] ?? null;
    }

    /** @return array<string, mixed> همه‌ی کلیدهای ذخیره‌شده */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            return $this->loaded = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (Throwable $e) {
            // دیتابیس هنوز آماده نیست (مثلاً پیش از migrate): با پیش‌فرض‌ها کار کن
            return [];
        }
    }

    /**
     * چند تنظیم را یک‌جا ذخیره می‌کند و تغییرها را برمی‌گرداند (برای تاریخچه‌ی فعالیت).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function setMany(array $values, ?int $userId = null): array
    {
        $current = $this->all();
        $changes = [];

        DB::transaction(function () use ($values, $userId, $current, &$changes): void {
            foreach ($values as $key => $value) {
                $value = is_string($value) ? trim($value) : $value;
                $value = $value === '' ? null : $value;
                $old = $current[$key] ?? null;
                if ($old === $value) {
                    continue;
                }
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => explode('.', $key)[0], 'updated_by' => $userId],
                );
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        });

        $this->flush();

        return $changes;
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $this->setMany([$key => $value], $userId);
    }

    /** cache را پاک می‌کند تا مقدار تازه خوانده شود. */
    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
