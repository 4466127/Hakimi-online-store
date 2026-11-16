<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Page;
use App\Models\Province;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * داده‌ی اولیه: ولایت‌ها، پول‌ها، صفحه‌ها، کاربر صاحب. تکرار seeder نباید چیزی را خراب کند.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_data_is_created(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(34, Province::query()->count());
        $this->assertSame(34, Province::query()->distinct()->count('code'));
        $this->assertSame('کابل', Province::query()->active()->first()->tr('name'));

        // تحویل فعلاً فقط در کابل (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵)
        $this->assertSame(['KAB'], Province::query()->deliverable()->pluck('code')->all());
        $this->assertSame(['کابل'], Province::deliverableNames());

        $afn = Currency::defaultCurrency();
        $this->assertSame('AFN', $afn->code);
        $this->assertFalse(Currency::query()->findOrFail('USD')->is_active);
        $this->assertSame('۲٬۵۰۰ افغانی', Money::format(2500));

        $this->assertEqualsCanonicalizing(
            ['about', 'contact', 'terms', 'returns', 'privacy'],
            Page::query()->pluck('slug')->all(),
        );

        $owner = User::query()->sole();
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertTrue($owner->mustChangePassword());

        // داده‌ی اولیه‌ی ولایت‌ها و صفحه‌ها در تاریخچه‌ی فعالیت نوشته نمی‌شود
        $this->assertSame(0, ActivityLog::query()->whereIn('subject_type', ['province', 'page'])->count());
    }

    public function test_running_the_seeder_again_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);

        // مدیر چیزهایی را عوض کرده است
        Province::query()->where('code', 'HER')->update(['delivery_enabled' => true]);
        Page::query()->where('slug', 'about')->firstOrFail()->update(['body' => ['fa' => '<p>متن مدیر</p>']]);
        $ownerPassword = User::query()->sole()->password;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(34, Province::query()->count());
        $this->assertTrue(Province::query()->where('code', 'HER')->firstOrFail()->delivery_enabled);
        $this->assertSame('<p>متن مدیر</p>', Page::query()->where('slug', 'about')->firstOrFail()->tr('body'));
        $this->assertSame(5, Page::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame($ownerPassword, User::query()->sole()->password);
    }
}
