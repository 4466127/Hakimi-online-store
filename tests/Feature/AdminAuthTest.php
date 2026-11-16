<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ورود مدیران به پنل: با شماره‌ی تلفن، قفل بعد از رمز اشتباه، تغییر اجباری رمز اولیه.
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee(__('admin.auth.phone'));
    }

    public function test_user_logs_in_with_phone_and_password(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);

        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(ActivityLog::query()->where('action', 'auth.login')->where('actor_id', $user->id)->exists());
    }

    public function test_phone_can_be_typed_with_persian_digits_spaces_or_country_code(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);

        foreach (['۰۷۰۰ ۱۲۳ ۴۵۶', '+93 700 123 456', '700123456'] as $typed) {
            auth()->logout();

            Livewire::test(Login::class)
                ->fillForm(['phone' => $typed, 'password' => 'password1'])
                ->call('authenticate')
                ->assertHasNoFormErrors();

            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_wrong_password_is_rejected_and_counted(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);

        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['phone']);

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->failed_logins);
    }

    public function test_account_is_locked_after_too_many_wrong_passwords(): void
    {
        config(['store.admin_lock_after_attempts' => 3, 'store.admin_lock_minutes' => 15]);
        $user = User::factory()->create(['phone' => '0700123456']);

        foreach (range(1, 3) as $attempt) {
            Livewire::test(Login::class)
                ->fillForm(['phone' => '0700123456', 'password' => 'wrong-password'])
                ->call('authenticate')
                ->assertHasFormErrors(['phone']);
        }

        $user->refresh();
        $this->assertTrue($user->isLocked());
        $this->assertSame(0, $user->failed_logins);
        $this->assertTrue(ActivityLog::query()->where('action', 'auth.locked')->exists());

        // حالا حتی رمز درست هم قبول نمی‌شود
        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate')
            ->assertHasFormErrors(['phone']);
        $this->assertGuest();
    }

    public function test_lock_ends_after_the_waiting_time(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);
        $user->forceFill(['locked_until' => now()->subMinute()])->save();

        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['phone' => '0700123456']);

        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate')
            ->assertHasFormErrors(['phone']);

        $this->assertGuest();
    }

    public function test_user_with_initial_password_must_change_it_first(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->get('/admin')->assertRedirect('/admin/profile');
        $this->actingAs($user)->get('/admin/profile')->assertOk()->assertSee(__('admin.profile.must_change'));
    }

    public function test_changing_own_password_removes_the_forced_change(): void
    {
        $user = User::factory()->mustChangePassword()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'password' => 'new-secret-123',
                'passwordConfirmation' => 'new-secret-123',
                'currentPassword' => 'password1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->mustChangePassword());
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
        $this->get('/admin')->assertOk();
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'password' => 'short',
                'passwordConfirmation' => 'short',
                'currentPassword' => 'password1',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }
}
