<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ثبت‌نام، ورود، خروج و حساب کاربری مشتری (جدا از پنل مدیریت).
 */
class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'احمد کریمی',
            'phone' => '۰۷۹۹ ۱۲۳ ۴۵۶',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ], $overrides);
    }

    public function test_guest_sees_login_link_in_header(): void
    {
        $this->get('/')->assertOk()->assertSee(__('store.nav.login'))->assertSee(route('login'), false);
    }

    public function test_customer_registers_with_dari_digits_and_is_logged_in(): void
    {
        $response = $this->post('/register', $this->registerData());

        $response->assertRedirect(route('account'));
        $customer = Customer::query()->firstOrFail();
        $this->assertSame('0799123456', $customer->phone);
        $this->assertAuthenticatedAs($customer, 'customer');
        // مشتری کاربر پنل نیست
        $this->assertGuest('web');

        $this->get('/account')->assertOk()->assertSee('احمد کریمی');
        $this->get('/')->assertSee('احمد کریمی');
    }

    public function test_registration_rejects_taken_phone_weak_password_and_missing_terms(): void
    {
        Customer::factory()->create(['phone' => '0799123456']);

        $this->post('/register', $this->registerData(['password' => 'short', 'password_confirmation' => 'short', 'terms' => null]))
            ->assertSessionHasErrors(['phone', 'password', 'terms']);

        $this->assertSame(1, Customer::query()->count());
        $this->assertGuest('customer');
    }

    public function test_registration_rejects_non_afghan_number(): void
    {
        $this->post('/register', $this->registerData(['phone' => '+98 912 123 4567']))->assertSessionHasErrors('phone');
    }

    public function test_customer_logs_in_and_out(): void
    {
        $customer = Customer::factory()->create(['phone' => '0700111222']);

        $this->post('/login', ['phone' => '+93 700 111 222', 'password' => CustomerFactory::PASSWORD])
            ->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertNotNull($customer->fresh()->last_login_at);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest('customer');
    }

    public function test_wrong_password_and_inactive_account_cannot_log_in(): void
    {
        Customer::factory()->create(['phone' => '0700111222']);
        Customer::factory()->inactive()->create(['phone' => '0700111333']);

        $this->post('/login', ['phone' => '0700111222', 'password' => 'wrong-pass1'])
            ->assertSessionHasErrors(['phone' => __('auth.failed')]);
        $this->post('/login', ['phone' => '0700111333', 'password' => CustomerFactory::PASSWORD])
            ->assertSessionHasErrors(['phone' => __('customer.login.inactive')]);

        $this->assertGuest('customer');
    }

    public function test_login_is_throttled_after_five_wrong_passwords(): void
    {
        Customer::factory()->create(['phone' => '0700111222']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['phone' => '0700111222', 'password' => 'wrong-pass1']);
        }

        // حتی رمز درست هم تا یک دقیقه قبول نمی‌شود
        $this->post('/login', ['phone' => '0700111222', 'password' => CustomerFactory::PASSWORD])
            ->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_account_needs_login_and_returns_to_it_after_login(): void
    {
        Customer::factory()->create(['phone' => '0700111222']);

        $this->get('/account')->assertRedirect(route('login'));

        $this->post('/login', ['phone' => '0700111222', 'password' => CustomerFactory::PASSWORD])
            ->assertRedirect('/account');
    }

    public function test_logged_in_customer_is_sent_from_login_page_to_account(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/login')->assertRedirect(route('account'));
    }

    public function test_customer_updates_name_and_password(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $this->put('/account/profile', ['name' => 'نام تازه', 'email' => ''])->assertSessionHasNoErrors();
        $this->assertSame('نام تازه', $customer->fresh()->name);
        $this->assertNull($customer->fresh()->email);

        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])
            ->assertSessionHasErrors('current_password');

        $this->put('/account/password', ['current_password' => CustomerFactory::PASSWORD, 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('newpass99', (string) $customer->fresh()->password));
    }

    public function test_customer_login_does_not_open_the_admin_panel(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_login_does_not_make_a_customer_session(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get('/account')->assertRedirect(route('login'));
    }
}
