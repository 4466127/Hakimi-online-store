<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * هر نقش فقط بخش‌های خودش را در پنل می‌بیند.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, string, bool}>
     */
    public static function accessMatrix(): array
    {
        $pages = [
            'dashboard' => '/admin',
            'users' => '/admin/users',
            'settings' => '/admin/settings',
            'provinces' => '/admin/provinces',
            'pages' => '/admin/pages',
            'activity' => '/admin/activity',
        ];

        // چه کسی کدام بخش را می‌بیند
        $allowed = [
            'owner' => ['dashboard', 'users', 'settings', 'provinces', 'pages', 'activity'],
            'manager' => ['dashboard', 'settings', 'provinces', 'pages', 'activity'],
            'staff' => ['dashboard'],
            'editor' => ['dashboard', 'pages'],
        ];

        $cases = [];
        foreach (UserRole::cases() as $role) {
            foreach ($pages as $name => $url) {
                $can = in_array($name, $allowed[$role->value], true);
                $cases[$role->value.' '.($can ? 'can' : 'cannot').' open '.$name] = [$role, $url, $can];
            }
        }

        return $cases;
    }

    #[DataProvider('accessMatrix')]
    public function test_role_access(UserRole $role, string $url, bool $allowed): void
    {
        $user = User::factory()->role($role)->create();

        $response = $this->actingAs($user)->get($url);

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }

    public function test_dashboard_is_in_dari_and_uses_local_font(): void
    {
        $response = $this->actingAs(User::factory()->owner()->create())->get('/admin');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('/fonts/vazirmatn/vazirmatn.css', false);
        $response->assertSee(__('admin.start.heading'));
        // هیچ فونت یا عکسی از سایت بیرونی
        $response->assertDontSee('fonts.bunny.net', false);
        $response->assertDontSee('ui-avatars.com', false);
    }

    public function test_owner_sees_users_in_the_menu(): void
    {
        $this->actingAs(User::factory()->owner()->create())->get('/admin')
            ->assertSee(__('admin.users.plural'))
            ->assertSee(__('admin.settings.title'));
    }

    public function test_manager_does_not_see_users_in_the_menu(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get('/admin')
            ->assertDontSee(__('admin.users.plural'))
            ->assertSee(__('admin.settings.title'));
    }

    public function test_staff_sees_neither_users_nor_settings_nor_pages_in_the_menu(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Staff)->create())->get('/admin')
            ->assertDontSee(__('admin.users.plural'))
            ->assertDontSee(__('admin.settings.title'))
            ->assertDontSee(__('admin.pages.plural'))
            ->assertDontSee(__('admin.provinces.plural'));
    }
}
