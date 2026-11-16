<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageStoreSettings;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تنظیمات فروشگاه: خواندن، نوشتن، cache، صفحه‌ی پنل.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_used_until_a_value_is_saved(): void
    {
        $settings = app(Settings::class);

        $this->assertSame('بازارک', $settings->get('store.name'));
        $this->assertNull($settings->get('store.phone'));
        $this->assertSame('x', $settings->get('unknown.key', 'x'));

        $settings->set('store.name', 'دکان آزمایشی');

        $this->assertSame('دکان آزمایشی', $settings->get('store.name'));
        // نمونه‌ی تازه‌ی کلاس هم همان مقدار را می‌خواند (از دیتابیس/cache)
        $this->assertSame('دکان آزمایشی', (new Settings)->get('store.name'));
    }

    public function test_set_many_reports_only_real_changes(): void
    {
        $settings = app(Settings::class);
        $settings->setMany(['store.name' => 'الف', 'store.phone' => '0700123456']);

        $changes = $settings->setMany(['store.name' => 'الف', 'store.phone' => '0799000111', 'store.email' => '']);

        $this->assertSame(['store.phone'], array_keys($changes));
        $this->assertSame('0700123456', $changes['store.phone']['old']);
        $this->assertSame('0799000111', $changes['store.phone']['new']);
        $this->assertSame(2, Setting::query()->count());
    }

    public function test_manager_saves_settings_from_the_panel(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        Livewire::test(ManageStoreSettings::class)
            ->assertFormSet(['name' => 'بازارک'])
            ->fillForm([
                'name' => 'فروشگاه حکیمی',
                'tagline' => 'بهترین قیمت',
                'phone' => '۰۷۰۰ ۱۲۳ ۴۵۶',
                'whatsapp' => '+93799111222',
                'address' => 'کابل، مارکیت آزمایشی',
                'is_open' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(Settings::class);
        $this->assertSame('فروشگاه حکیمی', $settings->get('store.name'));
        // شماره‌ها به شکل استاندارد ذخیره می‌شود
        $this->assertSame('0700123456', $settings->get('store.phone'));
        $this->assertSame('0799111222', $settings->get('store.whatsapp'));
        $this->assertTrue($settings->get('store.is_open'));

        $log = ActivityLog::query()->where('action', 'settings.updated')->firstOrFail();
        $this->assertSame($owner->id, $log->actor_id);
        $this->assertSame('فروشگاه حکیمی', $log->changes['store.name']['new']);

        // سایت همان لحظه نام تازه را نشان می‌دهد
        $this->get('/')->assertSee('فروشگاه حکیمی')->assertSee('https://wa.me/93799111222', false);
    }

    public function test_uploaded_logo_is_rebuilt_as_webp_and_shown_on_the_site(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 900, 900)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = app(Settings::class)->get('store.logo');

        $this->assertMatchesRegularExpression('#^store/[0-9a-f-]{36}\.webp$#', (string) $path);
        Storage::disk('public')->assertExists($path);
        // لوگو کوچک می‌شود (بزرگ‌ترین ضلع ۶۰۰ پیکسل)
        $this->assertSame(600, getimagesizefromstring(Storage::disk('public')->get($path))[0]);
        $this->get('/')->assertSee($path, false);
    }

    public function test_store_name_is_required_and_phone_must_be_valid(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ManageStoreSettings::class)
            ->fillForm(['name' => '', 'phone' => '12345'])
            ->call('save')
            ->assertHasFormErrors(['name', 'phone']);

        $this->assertSame(0, Setting::query()->count());
    }
}
