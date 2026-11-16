<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ساختن و تغییر کاربران پنل توسط صاحب فروشگاه.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->actingAs($this->owner);
    }

    public function test_owner_creates_a_user_with_normalised_phone_and_hashed_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'احمد حکیمی',
                'phone' => '۰۷۹۹ ۱۱۱ ۲۲۲',
                'role' => UserRole::Staff->value,
                'is_active' => true,
                'password' => 'first-pass-1',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('phone', '0799111222')->firstOrFail();

        $this->assertSame('احمد حکیمی', $user->name);
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertTrue(Hash::check('first-pass-1', $user->password));
        $this->assertNotEmpty($user->uuid);
        // رمز را صاحب داده است، پس کاربر باید در اولین ورود عوضش کند
        $this->assertTrue($user->mustChangePassword());
        $this->assertTrue(
            ActivityLog::query()->where('action', 'user.created')->where('subject_id', $user->id)
                ->where('actor_id', $this->owner->id)->exists()
        );
    }

    public function test_phone_must_be_a_valid_afghan_mobile_and_unique(): void
    {
        User::factory()->create(['phone' => '0799111222']);

        // شماره‌ی نادرست
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'الف', 'phone' => '12345', 'role' => 'staff', 'password' => 'first-pass-1'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        // همان شماره با شکل دیگر: تکراری حساب می‌شود
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'ب', 'phone' => '+93 799 111 222', 'role' => 'staff', 'password' => 'first-pass-1'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        $this->assertSame(2, User::query()->count());
    }

    public function test_required_fields_and_weak_password_are_rejected(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => '', 'phone' => '', 'password' => '123'])
            ->call('create')
            ->assertHasFormErrors(['name', 'phone', 'password']);
    }

    public function test_editing_a_user_without_password_keeps_the_old_password(): void
    {
        $user = User::factory()->create(['name' => 'نام قدیم']);
        $oldHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'نام تازه', 'role' => UserRole::Editor->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('نام تازه', $user->name);
        $this->assertSame(UserRole::Editor, $user->role);
        $this->assertSame($oldHash, $user->password);
        $this->assertFalse($user->mustChangePassword());

        // تاریخچه: نام و نقش نوشته می‌شود، رمز هیچ‌وقت
        $log = ActivityLog::query()->where('action', 'user.updated')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame('نام قدیم', $log->changes['name']['old']);
        $this->assertSame('نام تازه', $log->changes['name']['new']);
        $this->assertArrayNotHasKey('password', $log->changes);
    }

    public function test_when_owner_resets_a_password_the_user_must_change_it(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['password' => 'reset-pass-9'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('reset-pass-9', $user->password));
        $this->assertTrue($user->mustChangePassword());
    }

    public function test_nobody_can_delete_himself_or_the_last_owner(): void
    {
        $staff = User::factory()->create();
        $secondOwner = User::factory()->owner()->create();

        $this->assertTrue($this->owner->can('delete', $staff));
        $this->assertFalse($this->owner->can('delete', $this->owner));
        // دو صاحب هست: یکی می‌تواند دیگری را حذف کند
        $this->assertTrue($this->owner->can('delete', $secondOwner));

        $secondOwner->delete();
        // حالا فقط یک صاحبِ فعال مانده؛ حتی صاحب دیگری (غیرفعال) هم نمی‌تواند او را حذف کند
        $inactiveOwner = User::factory()->owner()->inactive()->create();
        $this->assertFalse($inactiveOwner->can('delete', $this->owner));
        $this->assertFalse($this->owner->can('deleteAny', User::class));
    }

    public function test_last_owner_cannot_be_demoted_or_deactivated_from_the_form(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->owner->getRouteKey()])
            ->assertFormFieldIsDisabled('role')
            ->assertFormFieldIsDisabled('is_active');
    }
}
