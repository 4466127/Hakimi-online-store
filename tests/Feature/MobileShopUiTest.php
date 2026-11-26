<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\HomeVideos\HomeVideoResource;
use App\Filament\Resources\HomeVideos\Pages\CreateHomeVideo;
use App\Http\Controllers\Storefront\AccountController;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\HomeVideo;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CategoryMenu;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * ظاهر موبایل به الگوی دیجی‌کالا (خواست صاحب پروژه، ۵ قوس ۱۴۰۵): کد تخفیف در سایت با کاپی و شمارش معکوس،
 * ویدیوی اجناس در صفحه‌ی اصلی، دسته‌ها در دو ستون، خانه‌های «سفارش‌های من»، نوار پایین و پشتیبانی شناور.
 */
class MobileShopUiTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function promo(array $attributes = []): DiscountCode
    {
        return DiscountCode::query()->create($attributes + [
            'code' => 'EID1405', 'type' => 'percent', 'value' => '10', 'is_active' => true,
            'show_on_site' => true, 'promo_title' => ['fa' => 'تخفیف عید'],
        ]);
    }

    private function video(array $attributes = []): HomeVideo
    {
        Storage::disk('public')->put($v = 'videos/'.uniqid().'.mp4', 'x');
        Storage::disk('public')->put($p = 'videos/'.uniqid().'.webp', 'x');

        return HomeVideo::query()->create($attributes + [
            'title' => ['fa' => 'بوت‌های زمستانی'], 'video_path' => $v, 'poster_path' => $p, 'position' => 1, 'is_active' => true,
        ]);
    }

    // ---------- کد تخفیف در سایت ----------

    public function test_home_shows_the_site_code_with_copy_and_countdown(): void
    {
        $this->promo(['ends_at' => now()->addHours(5), 'min_order_total' => '2000']);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="promo"', $html);
        $this->assertStringContainsString('تخفیف عید', $html);
        $this->assertStringContainsString('EID1405', $html);
        $this->assertStringContainsString(__('store.promo.copy'), $html);
        $this->assertStringContainsString('role="timer"', $html);
        // زمان باقی‌مانده از سرور (حدود ۵ ساعت)، نه از ساعت موبایل
        $this->assertMatchesRegularExpression('/remaining: (1[78]\d{3})/', $html);
        // پیام کوچک بالای نوار پایین
        $this->assertStringContainsString('bz-promo-hint-EID1405', $html);
    }

    public function test_code_without_end_date_has_no_countdown(): void
    {
        $this->promo();

        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('EID1405', $html);
        $this->assertStringNotContainsString('role="timer"', $html);
        $this->assertStringContainsString('remaining: null', $html);
    }

    public function test_hidden_off_expired_future_or_full_codes_are_never_shown(): void
    {
        $this->promo(['code' => 'HIDDEN1', 'show_on_site' => false]);
        $this->promo(['code' => 'OFFCODE', 'is_active' => false]);
        $this->promo(['code' => 'OLDCODE', 'ends_at' => now()->subMinute()]);
        $this->promo(['code' => 'LATERCODE', 'starts_at' => now()->addDay()]);
        $full = $this->promo(['code' => 'FULLCODE', 'max_uses' => 1]);
        $full->forceFill(['used_count' => 1])->save();

        $this->assertNull(DiscountCode::sitePromo());
        $this->get('/')->assertOk()->assertDontSee('id="promo"', false)
            ->assertDontSee('HIDDEN1')->assertDontSee('FULLCODE');
    }

    public function test_only_one_code_is_shown_the_latest_changed(): void
    {
        $this->promo(['code' => 'FIRST1']);
        $this->travel(1)->minutes();
        $this->promo(['code' => 'SECOND2']);

        $this->get('/')->assertOk()->assertSee('SECOND2')->assertDontSee('FIRST1');
    }

    public function test_checkout_offers_the_site_code_and_one_tap_applies_it(): void
    {
        $this->seedOrderBasics();
        $this->promo();
        $cart = $this->cartWith([[$this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 5]), 2]]);

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/checkout')->assertOk()
            ->assertSee('name="discount_code" value="EID1405"', false)
            ->assertSee(__('store.promo.use_now'));

        // دکمه‌ی «استفاده» بعد از خانه‌ی خالی متن است، پس مقدار دکمه فرستاده می‌شود
        $this->withCookie(CartService::COOKIE, $cart->token)
            ->post('/checkout/discount', ['discount_code' => 'EID1405'])
            ->assertRedirect(route('checkout.show'));

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/checkout')->assertOk()
            ->assertSee(__('checkout.discount.applied_box', ['code' => 'EID1405']))
            ->assertDontSee('name="discount_code" value="EID1405"', false);
    }

    public function test_manager_turns_on_show_on_site_and_must_write_the_card_text(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $code = $this->promo(['show_on_site' => false, 'promo_title' => null]);

        Livewire::test(EditDiscountCode::class, ['record' => $code->getRouteKey()])
            ->fillForm(['show_on_site' => true, 'promo_title.fa' => ''])
            ->call('save')
            ->assertHasFormErrors(['promo_title.fa' => 'required']);

        Livewire::test(EditDiscountCode::class, ['record' => $code->getRouteKey()])
            ->fillForm(['show_on_site' => true, 'promo_title.fa' => '۱۰٪ تخفیف اولین خرید'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($code->fresh()->show_on_site);
        $this->assertSame('۱۰٪ تخفیف اولین خرید', $code->fresh()->tr('promo_title'));
    }

    // ---------- ویدیوی صفحه‌ی اصلی ----------

    public function test_home_shows_active_videos_with_their_visible_products(): void
    {
        $this->seedOrderBasics();
        $shown = $this->sellableVariant(null, null, ['price' => '1500.00'])->product;
        $hidden = $this->sellableVariant(null, null, ['price' => '900.00'])->product;
        $hidden->update(['status' => 'draft']);
        $this->video(['product_ids' => [$shown->id, $hidden->id], 'link_url' => '/category/boot']);
        $this->video(['title' => ['fa' => 'ویدیوی خاموش'], 'is_active' => false]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<video', $html);
        $this->assertStringContainsString('preload="none"', $html);      // تا وقت پخش دانلود نمی‌شود
        $this->assertStringContainsString('muted', $html);
        $this->assertStringContainsString('playsinline', $html);
        $this->assertStringContainsString('homeVideo()', $html);
        $this->assertStringContainsString('بوت‌های زمستانی', $html);
        $this->assertStringContainsString($shown->tr('name'), $html);
        $this->assertStringNotContainsString($hidden->tr('name'), $html);
        $this->assertStringNotContainsString('ویدیوی خاموش', $html);
    }

    public function test_at_most_three_videos_can_be_active(): void
    {
        $this->video();
        $this->video();
        $this->video();

        $this->expectException(LogicException::class);
        $this->video();
    }

    public function test_editor_uploads_a_video_and_a_fake_video_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));

        // فایل متنی با پسوند mp4 پذیرفته نمی‌شود (نوع از محتوا بررسی می‌شود)
        Livewire::test(CreateHomeVideo::class)
            ->fillForm([
                'title.fa' => 'ویدیو',
                'video_path' => [UploadedFile::fake()->create('clip.mp4', 50, 'text/plain')],
                'poster_path' => [UploadedFile::fake()->image('poster.jpg', 800, 450)],
                'position' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['video_path']);

        Livewire::test(CreateHomeVideo::class)
            ->fillForm([
                'title.fa' => 'بوت‌های زمستانی',
                'video_path' => [UploadedFile::fake()->create('clip.mp4', 900, 'video/mp4')],
                'poster_path' => [UploadedFile::fake()->image('poster.jpg', 1600, 900)],
                'link_url' => '/category/boot',
                'position' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $video = HomeVideo::query()->sole();
        Storage::disk('public')->assertExists($video->video_path);
        $this->assertStringStartsWith('videos/', $video->video_path);
        $this->assertStringNotContainsString('clip', $video->video_path);   // نام تصادفی
        $this->assertStringEndsWith('.webp', $video->poster_path);           // عکس پیش‌نمایش WebP
        [$w] = getimagesizefromstring(Storage::disk('public')->get($video->poster_path));
        $this->assertLessThanOrEqual(1200, $w);
    }

    public function test_too_big_video_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

        Livewire::test(CreateHomeVideo::class)
            ->fillForm([
                'title.fa' => 'ویدیوی بزرگ',
                'video_path' => [UploadedFile::fake()->create('big.mp4', HomeVideo::MAX_VIDEO_KB + 100, 'video/mp4')],
                'poster_path' => [UploadedFile::fake()->image('poster.jpg', 800, 450)],
                'position' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['video_path']);
    }

    public function test_staff_and_couriers_cannot_manage_videos(): void
    {
        foreach ([UserRole::Staff, UserRole::Courier] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(HomeVideoResource::getUrl('index'))->assertForbidden();
        }
    }

    public function test_deleting_a_video_removes_its_files(): void
    {
        $video = $this->video();
        $video->delete();

        Storage::disk('public')->assertMissing($video->video_path);
        Storage::disk('public')->assertMissing($video->poster_path);
    }

    // ---------- دسته‌ها در دو ستون ----------

    public function test_categories_page_has_a_side_rail_and_opens_the_chosen_category(): void
    {
        $a = Category::factory()->create(['name' => ['fa' => 'پوشاک'], 'slug' => 'clothes', 'position' => 1]);
        $b = Category::factory()->create(['name' => ['fa' => 'موبایل و لوازم'], 'slug' => 'mobile', 'position' => 2]);
        Category::factory()->create(['name' => ['fa' => 'چارجر'], 'slug' => 'charger', 'parent_id' => $b->id]);
        Cache::forget(Category::MENU_CACHE_KEY);
        app()->forgetInstance(CategoryMenu::class);

        $html = (string) $this->get('/categories?c=mobile')->assertOk()->getContent();

        $this->assertStringContainsString(__('store.categories.rail_label'), $html);
        $this->assertStringContainsString('href="'.route('categories', ['c' => 'clothes']).'"', $html);
        $this->assertStringContainsString('active: 1', $html);                      // «موبایل و لوازم» انتخاب‌شده
        $this->assertStringContainsString(__('store.categories.show_all_products', ['name' => 'موبایل و لوازم']), $html);
        $this->assertStringContainsString('چارجر', $html);

        // slug ناشناخته: اولین دسته
        $this->assertStringContainsString('active: 0', (string) $this->get('/categories?c=nothing')->getContent());
    }

    // ---------- حساب: خانه‌های «سفارش‌های من» ----------

    public function test_account_shows_order_counts_per_group_and_the_list_filters_by_group(): void
    {
        $this->seedOrderBasics();
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $variant = $this->sellableVariant(null, null, ['price' => '1000.00', 'stock_qty' => 50]);

        $make = function (Customer $who, string $status) use ($variant): Order {
            $order = $this->placeOrder([[$variant, 1]], ['customerId' => $who->id]);
            $order->forceFill(['status' => $status])->save();

            return $order;
        };
        $current = $make($customer, 'pending');
        $make($customer, 'shipped');
        $delivered = $make($customer, 'delivered');
        $make($customer, 'cancelled');
        $make($other, 'delivered');   // سفارش کس دیگر شمرده نمی‌شود

        $this->actingAs($customer, 'customer');

        $this->assertSame(['current' => 2, 'delivered' => 1, 'returned' => 0, 'cancelled' => 1],
            AccountController::groupCounts($customer));

        $this->get('/account')->assertOk()
            ->assertSee(__('customer.account.groups.current'))
            ->assertSee(__('customer.account.groups.returned'))
            ->assertSee(route('account.orders', ['status' => 'delivered']), false);

        $this->get('/account/orders?status=delivered')->assertOk()
            ->assertSee($delivered->number)
            ->assertDontSee($current->number)
            ->assertSee(__('customer.account.groups_showing', ['group' => __('customer.account.groups.delivered')]));

        $this->get('/account/orders?status=returned')->assertOk()->assertSee(__('customer.account.groups_empty'));
        // مقدار ناشناخته = همه
        $this->get('/account/orders?status=hack')->assertOk()->assertSee($current->number)->assertSee($delivered->number);
    }

    // ---------- نوار پایین و پشتیبانی ----------

    public function test_bottom_bar_is_a_floating_pill_and_support_button_shows_on_home(): void
    {
        app(Settings::class)->set('store.whatsapp', '0700111222');

        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('rounded-full bg-white/95', $html);
        $this->assertStringContainsString(__('store.support.button'), $html);

        // در صفحه‌ی سبد دکمه‌ی پشتیبانی شناور نیست
        $this->assertStringNotContainsString(__('store.support.button'), (string) $this->get('/cart')->getContent());
    }
}
