<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Filament\Pages\ManageMarketplaceSettings;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\Seller;
use App\Models\SellerCommissionRate;
use App\Models\SellerTransaction;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\Commission\SaleLine;
use App\Services\Commission\SettlementException;
use App\Services\SellerOnboarding;
use App\Services\Settings;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * کمیسیون بازارک: نرخ‌ها (دسته، بالادست، پیش‌فرض، نرخ ویژه‌ی فروشنده)، ثبت فروش تحویل‌شده،
 * برگشت، تسویه، مزایای فروشندگان تازه و پنل.
 */
class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    private Category $mobile;

    private Category $phones;

    private Category $samsung;

    private Category $accessories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvinceSeeder::class);
        $this->service = app(CommissionService::class);
        $this->mobile = Category::factory()->create(['slug' => 'mobile']);
        $this->phones = Category::factory()->childOf($this->mobile)->create(['slug' => 'mobile-phones', 'commission_rate' => 3]);
        $this->samsung = Category::factory()->childOf($this->phones)->create(['slug' => 'samsung']);
        $this->accessories = Category::factory()->childOf($this->mobile)->create(['slug' => 'mobile-accessories', 'commission_rate' => 6]);
    }

    private function seller(array $attributes = []): Seller
    {
        return Seller::factory()->status(SellerStatus::Approved)->create($attributes);
    }

    private function sale(?Seller $seller, Category $category, string $amount, int $id = 1, string $shipping = '0'): CommissionEntry
    {
        return $this->service->recordSale(new SaleLine('order_item', $id, $seller, $category, $amount, $shipping, 'سفارش آزمایشی'));
    }

    // ───── نرخ‌ها ─────

    public function test_rate_comes_from_category_then_parent_then_default_setting(): void
    {
        $seller = $this->seller();

        $this->assertSame(['3.00', 'category'], [$this->service->rateFor($seller, $this->phones)->rate, $this->service->rateFor($seller, $this->phones)->source]);
        $this->assertSame('3.00', $this->service->rateFor($seller, $this->samsung)->rate);        // از بالادست
        $this->assertSame('6.00', $this->service->rateFor($seller, $this->accessories)->rate);
        $this->assertSame(['7.00', 'default'], [$this->service->rateFor($seller, $this->mobile)->rate, $this->service->rateFor($seller, $this->mobile)->source]);

        // پیش‌فرض از تنظیمات می‌آید، نه از کد
        app(Settings::class)->setMany(['marketplace.default_commission_rate' => 8.5]);
        $this->assertSame('8.50', $this->service->rateFor($seller, $this->mobile)->rate);
    }

    /**
     * ترتیب تازه (تصمیم ۳، مرحله‌ی ۴): نرخ عادی فروشنده بعد از نرخ دسته است؛
     * فقط نرخ «استثنا»ی فروشنده بر دسته غلبه می‌کند.
     */
    public function test_seller_exception_rates_override_category_rates_but_regular_ones_do_not(): void
    {
        $seller = $this->seller();
        SellerCommissionRate::create(['seller_id' => $seller->id, 'category_id' => null, 'rate' => 9]);   // عادی: دسته غلبه می‌کند
        $this->assertSame(['3.00', 'category'], [$this->service->rateFor($seller, $this->samsung)->rate, $this->service->rateFor($seller, $this->samsung)->source]);

        SellerCommissionRate::create(['seller_id' => $seller->id, 'category_id' => null, 'rate' => 5, 'is_exception' => true]);
        SellerCommissionRate::create(['seller_id' => $seller->id, 'category_id' => $this->phones->id, 'rate' => 2, 'is_exception' => true]);
        // استثنای ختم‌شده و استثنای آینده اثر ندارد
        SellerCommissionRate::create(['seller_id' => $seller->id, 'category_id' => $this->accessories->id, 'rate' => 1, 'is_exception' => true, 'ends_at' => now()->subDay()]);
        SellerCommissionRate::create(['seller_id' => $seller->id, 'category_id' => $this->accessories->id, 'rate' => 0, 'is_exception' => true, 'starts_at' => now()->addDay()]);

        $this->assertSame(['2.00', 'exception'], [$this->service->rateFor($seller, $this->samsung)->rate, $this->service->rateFor($seller, $this->samsung)->source]);
        $this->assertSame('5.00', $this->service->rateFor($seller, $this->accessories)->rate);   // استثنای «همه‌ی دسته‌ها»
        $this->assertSame('3.00', $this->service->rateFor($this->seller(), $this->samsung)->rate); // فروشنده‌ی دیگر
    }

    public function test_own_store_products_have_no_commission(): void
    {
        $this->assertSame(['0.00', 'platform'], [$this->service->rateFor(null, $this->phones)->rate, $this->service->rateFor(null, $this->phones)->source]);
    }

    // ───── ثبت فروش ─────

    public function test_delivered_sale_records_commission_and_credits_the_seller_without_shipping(): void
    {
        $seller = $this->seller();

        $entry = $this->sale($seller, $this->samsung, '10000', shipping: '250');

        $this->assertSame(['10000.00', '3.00', '300.00', '9700.00', 'category'], [
            $entry->gross_amount, $entry->commission_rate, $entry->commission_amount, $entry->seller_amount, $entry->rate_source,
        ]);
        $this->assertSame('9700.00', $this->service->balance($seller));
        $this->assertSame('earning', SellerTransaction::query()->sole()->type);
    }

    public function test_same_sale_is_never_recorded_twice(): void
    {
        $seller = $this->seller();

        $first = $this->sale($seller, $this->phones, '5000', id: 77);
        $second = $this->sale($seller, $this->phones, '5000', id: 77);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, CommissionEntry::query()->count());
        $this->assertSame(1, SellerTransaction::query()->count());
        $this->assertSame('4850.00', $this->service->balance($seller));
    }

    public function test_rate_change_does_not_touch_past_sales(): void
    {
        $seller = $this->seller();
        $entry = $this->sale($seller, $this->phones, '1000', id: 1);

        $this->phones->update(['commission_rate' => 10]);
        $later = $this->sale($seller, $this->phones, '1000', id: 2);

        $this->assertSame('30.00', $entry->fresh()->commission_amount);
        $this->assertSame('100.00', $later->commission_amount);
    }

    public function test_entries_and_transactions_are_immutable(): void
    {
        $entry = $this->sale($this->seller(), $this->phones, '1000');

        $this->expectException(LogicException::class);
        $entry->update(['commission_amount' => 0]);
    }

    public function test_own_store_sales_are_kept_separate(): void
    {
        $seller = $this->seller();
        $this->sale($seller, $this->phones, '10000', id: 1);
        $this->sale(null, $this->phones, '4000', id: 2);

        $totals = $this->service->totals();

        $this->assertSame('10000.00', $totals['marketplace_sales']);
        $this->assertSame('300.00', $totals['commission']);
        $this->assertSame('9700.00', $totals['seller_earnings']);
        $this->assertSame('4000.00', $totals['own_store_sales']);
        $this->assertSame('9700.00', $totals['outstanding']);
    }

    // ───── برگشت ─────

    public function test_partial_and_full_returns_reverse_commission_at_the_original_rate(): void
    {
        $seller = $this->seller();
        $sale = $this->sale($seller, $this->accessories, '1000');   // ۶٪: ۶۰ / ۹۴۰
        $this->accessories->update(['commission_rate' => 20]);     // تغییر نرخ اثر ندارد

        $partial = $this->service->recordReversal($sale, 'order_return:1', '250');
        $this->assertSame(['-250.00', '-15.00', '-235.00', '6.00'], [$partial->gross_amount, $partial->commission_amount, $partial->seller_amount, $partial->commission_rate]);
        $this->assertSame('705.00', $this->service->balance($seller));

        // تکرار همان برگشت چیزی ثبت نمی‌کند
        $this->service->recordReversal($sale, 'order_return:1', '250');
        $this->assertSame(2, CommissionEntry::query()->count());

        // باقی‌مانده: دقیقاً صفر می‌شود
        $this->service->recordReversal($sale, 'order_return:2');
        $this->assertSame('0.00', $this->service->balance($seller));
        $this->assertSame('0.00', (string) number_format((float) CommissionEntry::query()->sum('commission_amount'), 2, '.', ''));

        $this->expectException(InvalidArgumentException::class);
        $this->service->recordReversal($sale, 'order_return:3', '1');
    }

    // ───── حساب و تسویه ─────

    public function test_settlement_reduces_balance_and_cannot_exceed_it(): void
    {
        $seller = $this->seller();
        $owner = User::factory()->owner()->create();
        $this->sale($seller, $this->phones, '10000');

        $settlement = $this->service->settle($seller, '9000', 'hesabpay', $owner, 'HP-123');
        $this->assertSame('700.00', $this->service->balance($seller));
        $this->assertSame('payout', SellerTransaction::query()->where('settlement_id', $settlement->id)->value('type'));

        $this->expectExceptionObject(new SettlementException('more_than_eligible'));
        $this->service->settle($seller, '701', 'cash', $owner);
    }

    public function test_manual_adjustment_needs_a_reason(): void
    {
        $seller = $this->seller();
        $owner = User::factory()->owner()->create();

        $this->service->adjust($seller, '-150', 'جنس خراب به دکان برگشت', $owner);
        $this->assertSame('-150.00', $this->service->balance($seller));

        $this->expectException(InvalidArgumentException::class);
        $this->service->adjust($seller, '10', ' ', $owner);
    }

    // ───── مزایای فروشندگان تازه ─────

    public function test_social_seller_gets_zero_commission_for_ninety_days_on_first_approval(): void
    {
        $seller = Seller::factory()->create(['is_social_seller' => true]);
        $owner = User::factory()->owner()->create();

        app(SellerOnboarding::class)->approve($seller, $owner);

        $seller->refresh();
        $this->assertNotNull($seller->approved_at);
        $this->assertSame('0.00', $this->service->rateFor($seller, $this->phones)->rate);
        $this->assertSame('3.00', $this->service->rateFor($seller, $this->phones, now()->addDays(91))->rate);

        // بستن و تأیید دوباره، مزیت را تکرار نمی‌کند
        app(SellerOnboarding::class)->suspend($seller, $owner, 'آزمایش');
        app(SellerOnboarding::class)->approve($seller->fresh(), $owner);
        $this->assertSame(1, SellerCommissionRate::query()->count());
    }

    public function test_brand_owner_gets_free_pickup_and_weekly_settlement(): void
    {
        $seller = Seller::factory()->create(['has_brand' => true, 'brand_name' => 'چپلی کابل']);

        app(SellerOnboarding::class)->approve($seller, User::factory()->owner()->create());

        $seller->refresh();
        $this->assertSame('weekly', $seller->settlement_cycle);
        $this->assertTrue($seller->hasFreePickup());
        $this->assertSame(now()->addDays(90)->toDateString(), $seller->free_pickup_until->toDateString());
    }

    public function test_incentives_follow_settings_and_can_be_turned_off(): void
    {
        app(Settings::class)->setMany(['marketplace.social_free_commission_days' => 0]);
        $seller = Seller::factory()->create(['is_social_seller' => true]);

        app(SellerOnboarding::class)->approve($seller, User::factory()->owner()->create());

        $this->assertSame(0, SellerCommissionRate::query()->count());
        $this->assertSame('monthly', $seller->fresh()->settlement_cycle);
    }

    // ───── سایت و پنل ─────

    public function test_public_commission_page_shows_rates_from_the_database(): void
    {
        $this->accessories->update(['commission_rate' => 6.5]);

        $this->get('/sell/commissions')->assertOk()
            ->assertSee('۶٫۵٪')
            ->assertSee('۷٪')                                  // سایر اجناس (پیش‌فرض)
            ->assertSee(__('commission.public.rules.5'));      // کرایه جداست
        $this->get('/sell')->assertOk()->assertSee(route('seller.commissions'), false)
            ->assertSee(__('commission.incentives.social.title'));
    }

    public function test_approved_seller_sees_balance_and_special_rate(): void
    {
        $seller = Seller::factory()->create(['is_social_seller' => true]);
        app(SellerOnboarding::class)->approve($seller, User::factory()->owner()->create());
        $this->sale($seller->fresh(), $this->phones, '2000');

        $this->actingAs($seller->customer, 'customer')
            ->get('/sell/onboarding')->assertOk()
            ->assertSee(__('commission.seller_view.heading'))
            ->assertSee('۲٬۰۰۰');                               // ۰٪ کمیسیون: همه‌ی پول از فروشنده
    }

    public function test_owner_records_a_settlement_from_the_seller_page(): void
    {
        $seller = $this->seller(['approved_at' => now()]);
        $this->sale($seller, $this->phones, '1000');
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])
            ->callAction('settle', data: ['amount' => '500', 'method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertHasNoActionErrors();

        $this->assertSame('470.00', $this->service->balance($seller));
    }

    public function test_marketplace_settings_page_saves_default_rate(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create());

        Livewire::test(ManageMarketplaceSettings::class)
            ->fillForm(['default_commission_rate' => 6, 'social_free_commission_days' => 60])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('6.00', $this->service->defaultRate());
        $this->assertSame(60, (int) app(Settings::class)->get('marketplace.social_free_commission_days'));
    }

    public function test_finance_pages_are_for_owner_and_manager_only(): void
    {
        foreach (['/admin/commissions', '/admin/settlements', '/admin/marketplace-settings'] as $url) {
            $this->actingAs(User::factory()->owner()->create())->get($url)->assertOk();
            $this->actingAs(User::factory()->role(UserRole::Staff)->create())->get($url)->assertForbidden();
            $this->actingAs(User::factory()->role(UserRole::Editor)->create())->get($url)->assertForbidden();
        }
    }
}
