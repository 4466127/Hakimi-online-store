<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Filament\Resources\Sellers\SellerResource;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Province;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «در بازارک بفروشید»: فورم چهارقدمی و بررسی مدیر.
 */
class SellerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Category $mobile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProvinceSeeder::class);
        $this->mobile = Category::factory()->create(['slug' => 'mobile', 'name' => ['fa' => 'موبایل']]);
        $this->customer = Customer::factory()->create(['name' => 'نبی احمدی', 'phone' => '0788000111']);
    }

    private function infoData(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'full_name' => 'محمد نبی احمدی',
            'father_name' => 'عبدالله',
            'tazkira_number' => '۱۴۰۰ - ۰۱۰۱ - ۱۲۳۴۵',
            'store_name' => 'موبایل‌فروشی امید',
            'category_ids' => [$this->mobile->id],
        ], $overrides);
    }

    private function addressData(): array
    {
        return [
            'province_id' => Province::query()->where('code', 'KAB')->value('id'),
            'city' => 'کابل، ناحیه‌ی ۴',
            'address' => 'کارته‌ی چهار، سرک اول، دکان ۱۲',
            'phone' => '',
        ];
    }

    public function test_landing_page_is_public_and_linked_from_header(): void
    {
        $this->get('/')->assertSee(__('seller.nav_link'))->assertSee(route('seller.landing'), false);
        $this->get('/sell')->assertOk()->assertSee(__('seller.landing.heading'))->assertSee('تذکره');
    }

    public function test_onboarding_needs_login(): void
    {
        $this->get('/sell/onboarding')->assertRedirect(route('login'));
    }

    public function test_full_onboarding_flow_ends_pending_review(): void
    {
        $this->actingAs($this->customer, 'customer');

        $this->get('/sell/onboarding')->assertRedirect('/sell/onboarding/info');
        $this->get('/sell/onboarding/info')->assertOk()
            ->assertSee(__('seller.onboarding.info.type_heading'))
            ->assertSee('نبی احمدی');                         // نام از حساب پر شده

        $this->post('/sell/onboarding/info', $this->infoData())->assertRedirect('/sell/onboarding/address');
        $this->post('/sell/onboarding/address', $this->addressData())->assertRedirect('/sell/onboarding/rules');
        $this->post('/sell/onboarding/rules', ['terms' => '1'])->assertRedirect('/sell/onboarding/review');

        $this->get('/sell/onboarding/review')->assertOk()->assertSee('1400-0101-12345')->assertSee('کابل');

        $this->post('/sell/onboarding/submit')->assertRedirect('/sell/onboarding');

        $seller = Seller::query()->firstOrFail();
        $this->assertSame(SellerStatus::Pending, $seller->status);
        $this->assertSame('1400-0101-12345', $seller->tazkira_number);  // بی فاصله، ارقام انگلیسی
        $this->assertNull($seller->phone);   // تلفن دکان اختیاری است
        $this->assertNotNull($seller->submitted_at);
        $this->assertTrue(ActivityLog::query()->where('action', 'seller.submitted')->where('actor_type', 'customer')->exists());

        $this->get('/sell/onboarding')->assertOk()->assertSee(__('seller.status.pending_heading'));

        // درخواست فرستاده‌شده دیگر ویرایش نمی‌شود
        $this->post('/sell/onboarding/info', $this->infoData(['store_name' => 'نام دیگر']))->assertForbidden();
        $this->get('/sell/onboarding/info')->assertRedirect('/sell/onboarding');
    }

    public function test_later_steps_are_locked_until_earlier_ones_are_done(): void
    {
        $this->actingAs($this->customer, 'customer');

        $this->get('/sell/onboarding/address')->assertRedirect('/sell/onboarding');
        $this->post('/sell/onboarding/address', $this->addressData())->assertForbidden();
        $this->post('/sell/onboarding/submit')->assertForbidden();
    }

    public function test_info_step_validation(): void
    {
        $this->actingAs($this->customer, 'customer');
        $child = Category::factory()->childOf($this->mobile)->create();

        $this->post('/sell/onboarding/info', $this->infoData([
            'tazkira_number' => 'abc',
            'type' => 'business',               // شرکت: نام و جواز لازم
            'is_social_seller' => '1',          // اینستاگرامی: لینک لازم
            'category_ids' => [$child->id],     // فقط دسته‌ی اصلی
        ]))->assertSessionHasErrors(['tazkira_number', 'business_name', 'license_number', 'social_url', 'category_ids.0']);

        // فورم نادرست هیچ چیز ذخیره نمی‌کند
        $this->assertSame(0, Seller::query()->count());
    }

    public function test_tazkira_number_and_store_name_must_be_unique(): void
    {
        Seller::factory()->create(['tazkira_number' => '1400-0101-12345', 'store_name' => 'موبایل‌فروشی امید']);
        $this->actingAs($this->customer, 'customer');

        $this->post('/sell/onboarding/info', $this->infoData())
            ->assertSessionHasErrors(['tazkira_number', 'store_name']);
    }

    public function test_owner_approves_a_pending_seller(): void
    {
        $seller = Seller::factory()->create();
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])
            ->assertActionVisible('approve')
            ->assertActionVisible('reject')
            ->callAction('approve');

        $seller->refresh();
        $this->assertSame(SellerStatus::Approved, $seller->status);
        $this->assertSame($owner->id, $seller->reviewed_by);
        $this->assertTrue(ActivityLog::query()->where('action', 'seller.approved')->where('actor_id', $owner->id)->exists());
    }

    public function test_rejection_needs_a_reason_and_the_seller_can_fix_and_resubmit(): void
    {
        $seller = Seller::factory()->create(['customer_id' => $this->customer->id]);
        $this->actingAs(User::factory()->role(UserRole::Manager)->create());

        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        Livewire::test(ViewSeller::class, ['record' => $seller->getRouteKey()])
            ->callAction('reject', data: ['reason' => 'نمبر تذکره درست نیست'])
            ->assertHasNoActionErrors();

        $this->assertSame(SellerStatus::Rejected, $seller->fresh()->status);

        // فروشنده دلیل را می‌بیند، اصلاح می‌کند و دوباره می‌فرستد
        $this->actingAs($this->customer, 'customer');
        $this->get('/sell/onboarding')->assertOk()->assertSee('نمبر تذکره درست نیست');
        $this->post('/sell/onboarding/info', $this->infoData(['tazkira_number' => '1401-0202-55555']))->assertRedirect();
        $this->post('/sell/onboarding/submit')->assertRedirect('/sell/onboarding');

        $seller->refresh();
        $this->assertSame(SellerStatus::Pending, $seller->status);
        $this->assertNull($seller->review_note);
    }

    public function test_staff_and_editor_cannot_see_sellers(): void
    {
        $seller = Seller::factory()->create();

        foreach ([UserRole::Staff, UserRole::Editor] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/admin/sellers/'.$seller->id)->assertForbidden();
        }
    }

    public function test_pending_sellers_count_shows_in_the_admin_menu(): void
    {
        Seller::factory()->count(2)->create();
        Seller::factory()->status(SellerStatus::Approved)->create();

        $this->actingAs(User::factory()->owner()->create())
            ->get('/admin/sellers')->assertOk()->assertSee(__('admin.sellers.plural'));

        $this->assertSame('2', SellerResource::getNavigationBadge());
    }
}
