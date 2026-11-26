<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Province;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Payments\Gateways\HesabPayGateway;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * امنیت پیش از روشن کردن پرداخت آنلاین (مرحله‌ی ۸، ۵ قوس ۱۴۰۵):
 * CSP، اجازه‌ی GPS، ورود دومرحله‌ای پنل، نادیده گرفتن مبلغ و وضعیت ساختگی از مرورگر،
 * و فرستادن مشتری فقط به صفحه‌ی امن حساب‌پی.
 */
class SecurityHardeningTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    public function test_storefront_sends_a_strict_content_security_policy(): void
    {
        $csp = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        // کاشی نقشه‌ی تسویه
        $this->assertStringContainsString('https://tile.openstreetmap.org', $csp);
        // هیچ اسکریپت داخل صفحه ('unsafe-inline') در فروشگاه پذیرفته نمی‌شود
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
    }

    public function test_form_action_follows_the_configured_hesabpay_checkout_hosts(): void
    {
        // پیش از دانستن نام سایت پرداخت: هر https
        $this->assertStringContainsString("form-action 'self' https:", (string) $this->get('/')->headers->get('Content-Security-Policy'));

        config(['services.hesabpay.checkout_hosts' => ['checkout.hesab.com']]);
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self' https://checkout.hesab.com https://*.checkout.hesab.com", $csp);
        $this->assertStringNotContainsString('https:;', $csp);
    }

    public function test_admin_panel_gets_a_lighter_policy_that_does_not_break_filament(): void
    {
        $csp = (string) $this->get('/admin/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('script-src', $csp);
    }

    public function test_location_button_is_allowed_for_the_site_itself(): void
    {
        // پیش از این geolocation=() بود و دکمه‌ی «جای من» روی نقشه‌ی تسویه را کاملاً می‌بست
        $this->get('/')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }

    public function test_hesabpay_checkout_url_must_be_https_and_on_an_allowed_host(): void
    {
        $this->assertTrue(HesabPayGateway::isAllowedCheckoutUrl('https://checkout.example/checkout/1?data=x'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('http://checkout.example/checkout/1'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('javascript:alert(1)'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('https://user:pass@checkout.example/'));

        config(['services.hesabpay.checkout_hosts' => ['hesab.com']]);
        $this->assertTrue(HesabPayGateway::isAllowedCheckoutUrl('https://hesab.com/pay'));
        $this->assertTrue(HesabPayGateway::isAllowedCheckoutUrl('https://checkout.hesab.com/pay'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('https://checkout.example/pay'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('https://evilhesab.com/pay'));
        $this->assertFalse(HesabPayGateway::isAllowedCheckoutUrl('https://hesab.com.evil.example/pay'));
    }

    public function test_a_session_pointing_to_an_unexpected_site_is_refused(): void
    {
        $this->seedOrderBasics();
        config(['services.hesabpay.api_key' => 'test-key', 'services.hesabpay.env' => 'sandbox', 'services.hesabpay.api_url' => null,
            'services.hesabpay.checkout_hosts' => ['hesab.com']]);
        PaymentMethod::query()->where('code', PaymentMethod::HESABPAY)->update(['is_active' => true]);
        Http::fake([
            'https://api-sandbox.hesab.com/api/v1/payment/create-session' => Http::response([
                'success' => true, 'session_id' => 'sess-x', 'url' => 'https://phishing.example/checkout/sess-x',
            ]),
        ]);

        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1500.00', 'stock_qty' => 5]), 1]]);
        $response = $this->withCookie(CartService::COOKIE, $cart->token)->post('/checkout', $this->formData(['payment_method_code' => 'hesabpay']));

        // مشتری به سایت جعلی فرستاده نمی‌شود؛ صفحه‌ی سفارش خودمان باز می‌شود (دوباره امتحان یا نقد)
        $order = Order::query()->sole();
        $response->assertRedirect(route('order.show', ['uuid' => $order->uuid]));
        $this->assertNotSame(Payment::PAID, Payment::query()->first()?->status);
    }

    public function test_prices_totals_and_payment_status_sent_by_the_browser_are_ignored(): void
    {
        $this->seedOrderBasics();
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1500.00', 'stock_qty' => 5]), 2]]);

        $this->withCookie(CartService::COOKIE, $cart->token)->post('/checkout', $this->formData([
            'payment_method_code' => 'cod',
            // دستکاری: هیچ‌کدام نباید اثری داشته باشد
            'total' => '1', 'subtotal' => '1', 'price' => '1', 'delivery_fee' => '0', 'discount_total' => '2999',
            'payment_status' => 'paid', 'paid_total' => '3000', 'status' => 'delivered',
        ]))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame('3000.00', $order->subtotal);
        $this->assertTrue((float) $order->total >= 3000.0);
        $this->assertSame('0.00', $order->discount_total);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame('0.00', $order->paid_total);
        $this->assertNotSame('delivered', $order->status);
    }

    public function test_admin_with_two_step_login_must_enter_the_app_code(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $provider = collect(Filament::getMultiFactorAuthenticationProviders())->first(fn ($p) => $p instanceof AppAuthentication);
        $this->assertNotNull($provider, 'ورود دومرحله‌ای در پنل روشن نیست');

        $secret = $provider->generateSecret();
        $user->saveAppAuthenticationSecret($secret);
        // کلید در دیتابیس رمزنگاری‌شده است
        $this->assertNotSame($secret, User::query()->whereKey($user->id)->toBase()->value('app_authentication_secret'));

        // رمز درست به تنهایی کافی نیست
        $page = Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate');
        $this->assertGuest();
        $this->assertNotEmpty($page->get('userUndertakingMultiFactorAuthentication'));

        // کد اشتباه رد می‌شود
        $page->set('data.multiFactor.app.code', '000000')->call('authenticate');
        $this->assertGuest();

        // کد درست برنامه ← وارد می‌شود
        $page->set('data.multiFactor.app.code', $provider->getCurrentCode($user->fresh()))->call('authenticate');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_without_two_step_login_still_logs_in_with_password_only(): void
    {
        $user = User::factory()->create(['phone' => '0700123456']);

        Livewire::test(Login::class)
            ->fillForm(['phone' => '0700123456', 'password' => 'password1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    /** @param  array<string, mixed>  $overrides */
    private function formData(array $overrides = []): array
    {
        return $overrides + [
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل',
            'address_line' => 'کارته‌ی چهار',
            'delivery_date' => $this->firstDeliveryDate(),
        ];
    }
}
