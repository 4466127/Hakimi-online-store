<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\OrdersBySourceWidget;
use App\Models\Order;
use App\Models\Province;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Marketing\CampaignAttribution;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۹-۲: «از کدام تبلیغ آمد؟» — برچسب utm در کوکی ۳۰ روزه، روی سفارش، فیلتر پنل و کارت داشبورد.
 */
class CampaignAttributionTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    /** @return array<string, mixed>|null */
    private function cookieData(TestResponse $response): ?array
    {
        $cookie = $response->getCookie(CampaignAttribution::COOKIE, false);
        if ($cookie === null) {
            return null;
        }
        $plain = CookieValuePrefix::remove(app('encrypter')->decrypt((string) $cookie->getValue(), false));

        return json_decode($plain, true);
    }

    private function storedCookie(array $data): void
    {
        $this->withCookie(CampaignAttribution::COOKIE, (string) json_encode($data));
    }

    public function test_campaign_tags_are_captured_normalised_and_kept_thirty_days(): void
    {
        $response = $this->get('/?utm_source=Instagram&utm_medium=Social&utm_campaign='.urlencode('Eid Launch 2026!<script>'));

        $response->assertOk();
        $data = $this->cookieData($response);
        $this->assertSame('instagram', $data['source']);
        $this->assertSame('social', $data['medium']);
        $this->assertSame('eid-launch-2026script', $data['campaign']);
        $cookie = $response->getCookie(CampaignAttribution::COOKIE, false);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 120);
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_first_campaign_is_not_overwritten_by_later_visits(): void
    {
        $this->storedCookie(['source' => 'instagram', 'medium' => 'social', 'campaign' => 'launch', 'at' => now()->subDays(3)->getTimestamp()]);

        // برچسب دیگر یا بازدید بی برچسب: کوکی تازه فرستاده نمی‌شود
        $this->assertNull($this->get('/?utm_source=facebook&utm_campaign=other')->getCookie(CampaignAttribution::COOKIE, false));
        $this->assertNull($this->get('/')->getCookie(CampaignAttribution::COOKIE, false));
    }

    public function test_expired_or_invalid_tags_are_ignored(): void
    {
        // بیش از ۳۰ روز: برچسب تازه جایش را می‌گیرد
        $this->storedCookie(['source' => 'instagram', 'at' => now()->subDays(31)->getTimestamp()]);
        $data = $this->cookieData($this->get('/?utm_source=telegram'));
        $this->assertSame('telegram', $data['source']);
        $this->assertNull($data['medium']);

        // منبع نادرست (فقط نشانه) یا بی منبع: هیچ کوکی
        $this->assertNull($this->get('/?utm_source=%3C%3E%22')->getCookie(CampaignAttribution::COOKIE, false));
        $this->assertNull($this->get('/?utm_medium=social&utm_campaign=x')->getCookie(CampaignAttribution::COOKIE, false));

        $this->assertNull(CampaignAttribution::normalise(['array'], 'source'));
        $this->assertSame(50, strlen((string) CampaignAttribution::normalise(str_repeat('a', 80), 'source')));
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'purchaser_name' => 'احمد حکیمی',
            'purchaser_phone' => '0700111222',
            'delivery_method' => 'delivery',
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل',
            'address_line' => 'کارته‌ی چهار، سرک اول',
            'delivery_date' => $this->firstDeliveryDate(),
            'payment_method_code' => 'cod',
        ], $overrides);
    }

    public function test_guest_order_gets_the_stored_campaign_and_form_values_are_ignored(): void
    {
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 1]]);
        $this->withCookie(CartService::COOKIE, $cart->token);
        $this->storedCookie(['source' => 'facebook', 'medium' => 'social', 'campaign' => 'launch', 'at' => now()->subDays(2)->getTimestamp()]);

        $this->post('/checkout', $this->form(['utm_source' => 'hacked', 'utm_campaign' => 'hacked']))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->customer_id);
        $this->assertSame('facebook', $order->utm_source);
        $this->assertSame('social', $order->utm_medium);
        $this->assertSame('launch', $order->utm_campaign);
        $this->assertSame('web', $order->source);
    }

    public function test_order_without_campaign_has_no_tags(): void
    {
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 1]]);
        $this->withCookie(CartService::COOKIE, $cart->token);

        $this->post('/checkout', $this->form())->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->utm_source);
        $this->assertNull($order->utm_campaign);
    }

    public function test_admin_can_filter_orders_by_campaign_and_dashboard_counts_valid_orders_by_source(): void
    {
        $insta = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 9]), 1]], ['utmSource' => 'instagram', 'utmMedium' => 'social', 'utmCampaign' => 'launch']);
        $insta2 = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '2000.00', 'stock_qty' => 9]), 1]], ['utmSource' => 'instagram', 'utmMedium' => 'story', 'utmCampaign' => 'eid', 'purchaserPhone' => '0700111333']);
        $face = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '500.00', 'stock_qty' => 9]), 1]], ['utmSource' => 'facebook', 'utmMedium' => 'social', 'utmCampaign' => 'launch', 'purchaserPhone' => '0700111444']);
        $direct = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '700.00', 'stock_qty' => 9]), 1]], ['purchaserPhone' => '0700111555']);
        $cancelled = $this->placeOrder([[$this->sellableVariant(null, null, ['price' => '9000.00', 'stock_qty' => 9]), 1]], ['utmSource' => 'facebook', 'purchaserPhone' => '0700111666']);
        $cancelled->forceFill(['status' => Order::STATUS_CANCELLED])->save();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        Livewire::test(ListOrders::class)
            ->filterTable('utm_source', 'instagram')
            ->assertCanSeeTableRecords([$insta, $insta2])
            ->assertCanNotSeeTableRecords([$face, $direct])
            ->resetTableFilters()
            ->filterTable('utm_campaign', 'launch')
            ->assertCanSeeTableRecords([$insta, $face])
            ->assertCanNotSeeTableRecords([$insta2, $direct])
            ->resetTableFilters()
            ->filterTable('utm_medium', 'story')
            ->assertCanSeeTableRecords([$insta2])
            ->assertCanNotSeeTableRecords([$insta, $face]);

        $rows = collect(OrdersBySourceWidget::rows())->keyBy('source');
        $this->assertSame('۲', $rows['instagram']['all_count']);
        $this->assertStringContainsString('۳٬۴۰۰', $rows['instagram']['all_total']);    // ۱۰۰۰ + ۲۰۰۰ + دو کرایه‌ی ۲۰۰
        $this->assertSame('۱', $rows['facebook']['all_count']);                          // لغوشده حساب نمی‌شود
        $this->assertSame('۱', $rows[__('admin.campaigns.direct')]['recent_count']);

        Livewire::test(OrdersBySourceWidget::class)->assertSee('instagram')->assertSee(__('admin.campaigns.widget_heading'));
    }

    public function test_source_widget_is_only_for_owner_and_manager(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->assertFalse(OrdersBySourceWidget::canView());

        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $this->assertTrue(OrdersBySourceWidget::canView());
    }
}
