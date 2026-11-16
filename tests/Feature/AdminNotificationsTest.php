<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Provinces\Pages\ListProvinces;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Province;
use App\Models\User;
use App\Support\AdminNotify;
use Database\Seeders\ProvinceSeeder;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تصمیم صاحب پروژه (۲۶ عقرب ۱۴۰۵): بعد از هر کار موفق در پنل اعلان سبز ۸ ثانیه‌ای؛ در خطا اعلان سرخ با دلیل.
 * اشکال: «جنس را ذخیره می‌زنم و هیچ چیزی نشان نمی‌دهد».
 */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    /** آخرین اعلانی که فرستاده شده، به شکل آرایه (status، duration، title، body). @return array<string, mixed> */
    private function lastNotification(): array
    {
        app(NotificationMailbox::class)->flush();
        $component = new Notifications;
        $component->mount();
        $component->pullNotifications();

        return $component->notifications->last()->toArray();
    }

    private function category(): Category
    {
        $category = Category::factory()->create(['name' => ['fa' => 'بوت']]);
        $size = Attribute::factory()->create(['name' => ['fa' => 'سایز'], 'is_variant' => true]);
        $size->options()->create(['slug' => '42', 'value' => ['fa' => '42']]);
        $category->catalogAttributes()->attach($size->id, ['is_required' => false, 'position' => 1]);

        return $category;
    }

    public function test_every_notification_is_shown_for_eight_seconds_by_default(): void
    {
        $this->assertSame(8000, Notification::make()->success()->title('x')->getDuration());
        $this->assertSame(8000, AdminNotify::SUCCESS_MS);
    }

    public function test_successful_save_shows_a_green_notification(): void
    {
        $category = $this->category();
        $option = $category->effectiveAttributes()->first()->options()->first();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'بوت چرمی'],
                'category_id' => $category->id,
                'status' => 'draft',
                'variants' => [['price' => '1000', 'stock_qty' => '3', 'is_active' => true, 'option_ids' => [$option->id]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.products.saved'));
    }

    public function test_a_failed_save_shows_a_red_notification_with_the_reasons(): void
    {
        // نام و دسته را ننوشته: خطای قاعده‌های فورم
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => ['fa' => ''], 'category_id' => null])
            ->call('create')
            ->assertHasFormErrors(['name.fa', 'category_id'])
            ->assertNotified(__('admin.notify.failed_title'));
    }

    public function test_a_failed_save_from_the_service_rules_is_also_notified(): void
    {
        $category = $this->category();
        $option = $category->effectiveAttributes()->first()->options()->first();

        // دو نوع با یک ترکیب: خطای ProductService که ممکن است داخل بخش بسته‌ی فورم باشد
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'بوت'],
                'category_id' => $category->id,
                'status' => 'active',
                'variants' => [
                    ['price' => '100', 'stock_qty' => '1', 'is_active' => true, 'option_ids' => [$option->id]],
                    ['price' => '200', 'stock_qty' => '1', 'is_active' => true, 'option_ids' => [$option->id]],
                ],
            ])
            ->call('create')
            ->assertNotified(__('admin.notify.failed_title'));
    }

    public function test_error_notification_is_red_lasts_longer_and_lists_the_reasons(): void
    {
        AdminNotify::validationFailed(ValidationException::withMessages([
            'a' => ['قیمت لازم است.'],
            'b' => ['قیمت لازم است.', 'سایز درست نیست.'],
            'c' => ['یک'], 'd' => ['دو'], 'e' => ['سه'], 'f' => ['چهار'],
        ]));

        $notification = $this->lastNotification();
        $this->assertSame('danger', $notification['status']);
        $this->assertSame(AdminNotify::ERROR_MS, $notification['duration'], 'خطا ۱۵ ثانیه می‌ماند و بعد خودش بسته می‌شود');
        $this->assertGreaterThan(AdminNotify::SUCCESS_MS, AdminNotify::ERROR_MS);
        $this->assertSame(__('admin.notify.failed_title'), $notification['title']);
        $this->assertStringContainsString('قیمت لازم است.', (string) $notification['body']);
        $this->assertStringContainsString('سایز درست نیست.', (string) $notification['body']);
        $this->assertSame(1, substr_count((string) $notification['body'], 'قیمت لازم است.'), 'دلیل تکراری یک بار');
        $this->assertStringContainsString(__('admin.notify.more', ['count' => '۱']), (string) $notification['body']);   // ۶ دلیل: ۵ تا + «۱ مورد دیگر»
    }

    public function test_success_helper_is_green_and_eight_seconds(): void
    {
        AdminNotify::done('ذخیره شد', 'جنس تازه ساخته شد');

        $notification = $this->lastNotification();
        $this->assertSame('success', $notification['status']);
        $this->assertSame(8000, $notification['duration']);
        $this->assertSame('جنس تازه ساخته شد', $notification['body']);
    }

    public function test_creating_a_user_shows_a_notification_and_a_wrong_phone_a_red_one(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'علی', 'phone' => '12345', 'role' => 'staff', 'password' => 'first-pass-1'])
            ->call('create')
            ->assertHasFormErrors(['phone'])
            ->assertNotified(__('admin.notify.failed_title'));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'علی', 'phone' => '0799111222', 'role' => 'staff', 'password' => 'first-pass-1'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();
    }

    public function test_table_switches_say_saved(): void
    {
        $this->seed(ProvinceSeeder::class);
        $herat = Province::query()->where('code', 'HER')->firstOrFail();

        Livewire::test(ListProvinces::class)
            ->call('updateTableColumnState', 'delivery_enabled', $herat->getKey(), true)
            ->assertNotified(__('admin.saved'));

        $this->assertTrue($herat->fresh()->delivery_enabled);
    }

    public function test_live_checks_while_typing_do_not_show_a_red_notification(): void
    {
        // «حساب من»: شماره‌ی نادرست و رد شدن از خانه (blur) فقط زیر خانه می‌نویسد، اعلان سرخ نمی‌دهد
        $user = User::query()->firstOrFail();
        $user->forceFill(['password_changed_at' => now()])->saveQuietly();

        $component = Livewire::test(EditProfile::class)
            ->set('data.phone', '12345');

        $component->assertNotNotified(__('admin.notify.failed_title'));
    }
}
