<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صاحب پروژه نتوانست شماره‌ی تلفن مدیر را عوض کند: در «حساب من» قفل بود و کاربرِ رمز اولیه
 * فقط به همان صفحه می‌رسید. حالا هر کاربر شماره‌ی خودش را (با رمز فعلی) عوض می‌کند.
 */
class ProfilePhoneTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => UserRole::Owner, 'phone' => '0700000000', 'password' => 'Old-pass-123']);
        $user->forceFill(['password_changed_at' => now()])->saveQuietly();
        $this->actingAs($user);

        return $user;
    }

    private function profile(): Testable
    {
        return Livewire::test(EditProfile::class);
    }

    public function test_user_changes_own_phone_with_the_current_password(): void
    {
        $user = $this->user();

        $this->profile()
            ->fillForm(['name' => $user->name, 'phone' => '۰۷۹۹ ۱۲۳ ۴۵۶', 'currentPassword' => 'Old-pass-123'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0799123456', $user->fresh()->phone);   // شکل استاندارد
    }

    public function test_changing_the_phone_without_or_with_a_wrong_current_password_is_refused(): void
    {
        $user = $this->user();

        $this->profile()->fillForm(['name' => $user->name, 'phone' => '0799123456'])->call('save')->assertHasFormErrors(['currentPassword']);
        $this->profile()->fillForm(['name' => $user->name, 'phone' => '0799123456', 'currentPassword' => 'wrong'])->call('save')->assertHasFormErrors(['currentPassword']);

        $this->assertSame('0700000000', $user->fresh()->phone);
    }

    public function test_invalid_or_taken_phone_is_refused_and_unchanged_phone_needs_no_password(): void
    {
        $user = $this->user();
        User::factory()->create(['phone' => '0799111222']);

        $this->profile()->fillForm(['name' => $user->name, 'phone' => '12345', 'currentPassword' => 'Old-pass-123'])->call('save')->assertHasFormErrors(['phone']);
        $this->profile()->fillForm(['name' => $user->name, 'phone' => '+93 799 111 222', 'currentPassword' => 'Old-pass-123'])->call('save')->assertHasFormErrors(['phone']);

        // همان شماره (حتی با شکل نوشتن دیگر) و فقط تغییر نام: رمز فعلی لازم نیست
        $this->profile()->fillForm(['name' => 'نام تازه', 'phone' => '+93 700 000 000'])->call('save')->assertHasNoFormErrors();
        $fresh = $user->fresh();
        $this->assertSame('نام تازه', $fresh->name);
        $this->assertSame('0700000000', $fresh->phone);
    }

    public function test_user_with_the_initial_password_can_set_phone_and_password_together(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner, 'phone' => '0700000000', 'password' => 'Initial-pass-1']);
        $user->forceFill(['password_changed_at' => null])->saveQuietly();
        $this->actingAs($user);
        $this->assertTrue($user->fresh()->mustChangePassword());

        $this->profile()
            ->fillForm([
                'name' => $user->name,
                'phone' => '0799123456',
                'password' => 'Brand-new-pass-2',
                'passwordConfirmation' => 'Brand-new-pass-2',
                'currentPassword' => 'Initial-pass-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $user->fresh();
        $this->assertSame('0799123456', $fresh->phone);
        $this->assertFalse($fresh->mustChangePassword());
    }

    public function test_owner_can_also_change_a_phone_from_the_users_section(): void
    {
        $owner = $this->user();
        $other = User::factory()->create(['role' => UserRole::Staff, 'phone' => '0788111222']);

        Livewire::test(EditUser::class, ['record' => $other->getKey()])
            ->fillForm(['name' => $other->name, 'phone' => '0788333444', 'role' => 'staff'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('0788333444', $other->fresh()->phone);

        // و شماره‌ی خود صاحب را هم
        Livewire::test(EditUser::class, ['record' => $owner->getKey()])
            ->fillForm(['name' => $owner->name, 'phone' => '0799555666', 'role' => 'owner'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('0799555666', $owner->fresh()->phone);
    }
}
