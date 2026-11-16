<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\Cart\CartService;
use App\Support\Money;
use Database\Seeders\DeliveryZoneSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * مرحله‌ی ۴-۱: سبد خرید مهمان و عضو، موجودی و سقف تعداد، قیمت زنده، ادغام بعد از ورود.
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $variant */
    private function variant(array $variant = [], bool $active = true): ProductVariant
    {
        $product = $active ? Product::factory()->active()->withVariant($variant)->create() : Product::factory()->withVariant($variant)->create();

        return $product->variants()->firstOrFail();
    }

    private function token(TestResponse $response): ?string
    {
        $cookie = $response->getCookie(CartService::COOKIE);

        return $cookie?->getValue();
    }

    public function test_guest_adds_an_item_gets_a_cart_cookie_and_sees_it_with_live_price(): void
    {
        $variant = $this->variant(['price' => '1500.00', 'compare_at_price' => '2000.00', 'stock_qty' => 5]);

        $response = $this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 2]);
        $response->assertRedirect(route('cart.show'))->assertSessionHas('status', __('cart.added'));
        $token = $this->token($response);
        $this->assertNotNull($token);

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(2, CartItem::query()->firstOrFail()->qty);

        $page = $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk()
            ->assertSee($variant->product->tr('name'))
            ->assertSee(Money::format('1500'))
            ->assertSee(Money::format('3000'))                       // جمع اجناس ۲ × ۱۵۰۰
            ->assertSee(__('cart.savings'))
            ->assertSee(Money::format('1000'));                      // سود تخفیف ۲ × ۵۰۰

        // قیمت سبد زنده است: مدیر قیمت را عوض می‌کند، سبد همان لحظه تازه می‌شود
        $variant->update(['price' => '1200.00']);
        $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertSee(Money::format('2400'));
    }

    public function test_header_counter_shows_the_number_of_items(): void
    {
        $variant = $this->variant(['stock_qty' => 9]);
        $token = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 3]));

        $html = $this->withCookie(CartService::COOKIE, $token)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('۳', $html);
        $this->assertStringContainsString(__('cart.cart_count', ['count' => '۳']), $html);

        $this->defaultCookies = [];   // withCookie در کل تست می‌ماند؛ این بازدیدکننده‌ی دیگری است
        $this->get('/')->assertDontSee(__('cart.cart_count', ['count' => '۳']));   // بازدیدکننده‌ی دیگر سبد ندارد
    }

    public function test_adding_the_same_variant_again_adds_up_but_never_beyond_stock(): void
    {
        $variant = $this->variant(['stock_qty' => 4]);
        $token = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 3]));

        $again = $this->withCookie(CartService::COOKIE, $token)->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 3]);

        $again->assertSessionHas('warning', __('cart.clamped', ['n' => '۴']));
        $this->assertSame(4, CartItem::query()->firstOrFail()->qty);
        $this->assertSame(1, Cart::query()->count(), 'همان سبد استفاده شد');
    }

    public function test_reserved_stock_is_not_for_sale_and_backorder_variants_ignore_stock(): void
    {
        $reserved = $this->variant(['stock_qty' => 5]);
        $reserved->forceFill(['reserved_qty' => 5])->save();
        $this->post('/cart/add', ['variant_id' => $reserved->id])->assertSessionHas('error', __('cart.errors.out_of_stock'));
        $this->assertSame(0, CartItem::query()->count());

        $backorder = $this->variant(['stock_qty' => 0, 'allow_backorder' => true]);
        $this->post('/cart/add', ['variant_id' => $backorder->id, 'qty' => 5])->assertRedirect(route('cart.show'));
        $this->assertSame(5, CartItem::query()->firstOrFail()->qty);
    }

    public function test_line_quantity_is_capped_by_config(): void
    {
        config(['store.cart.max_qty_per_line' => 3]);
        $variant = $this->variant(['stock_qty' => 50]);

        $token = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 10]));

        $this->assertNotNull($token);
        $this->assertSame(3, CartItem::query()->firstOrFail()->qty);
    }

    public function test_hidden_or_inactive_things_cannot_be_added(): void
    {
        $draft = $this->variant([], false);
        $this->post('/cart/add', ['variant_id' => $draft->id])->assertSessionHas('error', __('cart.errors.unavailable'));

        $off = $this->variant();
        $off->update(['is_active' => false]);
        $this->post('/cart/add', ['variant_id' => $off->id])->assertSessionHas('error', __('cart.errors.unavailable'));

        $suspended = Seller::factory()->status(SellerStatus::Suspended)->create();
        $sold = Product::factory()->active()->ownedBy($suspended->id)->withVariant()->create();
        $this->post('/cart/add', ['variant_id' => $sold->variants()->value('id')])->assertSessionHas('error', __('cart.errors.unavailable'));

        $this->post('/cart/add', ['variant_id' => 999999])->assertSessionHas('error', __('cart.errors.unavailable'));
        $this->post('/cart/add', ['variant_id' => 'abc'])->assertSessionHasErrors('variant_id');
        $this->post('/cart/add', ['variant_id' => $this->variant()->id, 'qty' => 0])->assertSessionHasErrors('qty');
        $this->post('/cart/add', ['variant_id' => $this->variant()->id, 'qty' => 100])->assertSessionHasErrors('qty');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_changing_quantity_removing_and_zero(): void
    {
        $variant = $this->variant(['stock_qty' => 6]);
        $token = $this->token($this->post('/cart/add', ['variant_id' => $variant->id]));
        $item = CartItem::query()->firstOrFail();

        $this->withCookie(CartService::COOKIE, $token)->patch('/cart/items/'.$item->id, ['qty' => 4])->assertRedirect(route('cart.show'));
        $this->assertSame(4, $item->fresh()->qty);

        $this->withCookie(CartService::COOKIE, $token)->patch('/cart/items/'.$item->id, ['qty' => 9])
            ->assertSessionHas('warning', __('cart.clamped', ['n' => '۶']));
        $this->assertSame(6, $item->fresh()->qty);

        $this->withCookie(CartService::COOKIE, $token)->patch('/cart/items/'.$item->id, ['qty' => 0]);
        $this->assertNull(CartItem::query()->find($item->id));

        $item = $this->withCookie(CartService::COOKIE, $token)->post('/cart/add', ['variant_id' => $variant->id]) && CartItem::query()->firstOrFail();
        $this->withCookie(CartService::COOKIE, $token)->delete('/cart/items/'.CartItem::query()->firstOrFail()->id)->assertSessionHas('status', __('cart.removed'));
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_nobody_can_touch_another_visitors_cart_lines(): void
    {
        $variant = $this->variant(['stock_qty' => 9]);
        $mine = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 2]));
        $other = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 5]));
        $this->assertNotSame($mine, $other);

        $theirs = Cart::query()->where('token', $other)->firstOrFail()->items()->firstOrFail();

        $this->withCookie(CartService::COOKIE, $mine)->patch('/cart/items/'.$theirs->id, ['qty' => 1])->assertSessionHas('error', __('cart.errors.not_found'));
        $this->withCookie(CartService::COOKIE, $mine)->delete('/cart/items/'.$theirs->id);
        $this->patch('/cart/items/'.$theirs->id, ['qty' => 1])->assertSessionHas('error');   // بی سبد

        $this->assertSame(5, $theirs->fresh()->qty);
    }

    public function test_a_broken_cookie_is_ignored(): void
    {
        $variant = $this->variant();
        $this->withCookie(CartService::COOKIE, 'not-a-uuid')->post('/cart/add', ['variant_id' => $variant->id])->assertRedirect(route('cart.show'));
        $this->withCookie(CartService::COOKIE, 'not-a-uuid')->get('/cart')->assertOk();

        $this->assertSame(1, Cart::query()->count());
    }

    public function test_cart_page_flags_changed_lines_and_excludes_them_from_the_total(): void
    {
        $ok = $this->variant(['price' => '1000.00', 'stock_qty' => 9]);
        $low = $this->variant(['price' => '500.00', 'stock_qty' => 5]);
        $gone = $this->variant(['price' => '700.00', 'stock_qty' => 5]);
        $out = $this->variant(['price' => '300.00', 'stock_qty' => 5]);

        $token = $this->token($this->post('/cart/add', ['variant_id' => $ok->id, 'qty' => 1]));
        foreach ([[$low, 5], [$gone, 1], [$out, 2]] as [$v, $q]) {
            $this->withCookie(CartService::COOKIE, $token)->post('/cart/add', ['variant_id' => $v->id, 'qty' => $q]);
        }

        // بعد از گذاشتن در سبد: موجودی کم شد، یکی فروخته نمی‌شود، یکی تمام شد
        $low->update(['stock_qty' => 2]);
        $gone->product->update(['status' => 'inactive']);
        $out->update(['stock_qty' => 0]);

        $page = $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk()
            ->assertSee(__('cart.problem_banner'))
            ->assertSee(__('cart.problems.insufficient', ['n' => '۲']))
            ->assertSee(__('cart.problems.unavailable'))
            ->assertSee(__('cart.problems.out_of_stock'));

        // جمع = ۱ × ۱۰۰۰ + ۲ × ۵۰۰ (تعداد تا سقف موجودی)؛ دو خط ناموجود حساب نمی‌شود
        $page->assertSee(Money::format('2000'));
        $page->assertSee(__('cart.subtotal', ['count' => '۳']));
    }

    public function test_totals_weight_and_delivery_estimate_use_variant_weight_default_weight_and_zone_fees(): void
    {
        $this->seed(ProvinceSeeder::class);
        $this->seed(DeliveryZoneSeeder::class);
        $heavy = $this->variant(['stock_qty' => 9, 'weight_grams' => 4000]);
        $plain = $this->variant(['stock_qty' => 9]);   // وزن ندارد ← ۵۰۰ گرام

        $request = Request::create('/cart');
        $service = app(CartService::class);
        $cart = $service->resolve($request);
        $service->add($cart, $heavy->id, 2);   // ۸٬۰۰۰
        $service->add($cart, $plain->id, 3);   // ۱٬۵۰۰

        $totals = $service->totals($service->lines($cart));
        $this->assertSame(9500, $totals['weight']);
        $this->assertSame(5, $totals['units']);

        // ۹٫۵ کیلو: پله‌ی ۱۰ کیلو (+۱۵۰) ← کمترین ۱۰۰+۱۵۰=۲۵۰، بیشترین ۲۰۰+۱۵۰=۳۵۰
        $estimate = $service->deliveryEstimate($totals['weight']);
        $this->assertSame(['min' => '250.00', 'max' => '350.00'], $estimate);
        // سبک: ۱۰۰ تا ۲۰۰
        $this->assertSame(['min' => '100.00', 'max' => '200.00'], $service->deliveryEstimate(1200));

        DeliveryZone::query()->update(['max_weight_grams' => 5000]);
        $this->assertSame(['tooHeavy' => true], $service->deliveryEstimate(9500));

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/cart')->assertOk()->assertSee(__('cart.delivery_too_heavy'));
    }

    public function test_member_cart_belongs_to_the_customer_and_guest_cart_is_merged_after_login(): void
    {
        $a = $this->variant(['stock_qty' => 5]);
        $b = $this->variant(['stock_qty' => 5]);
        $gone = $this->variant(['stock_qty' => 5]);

        // مشتری از قبل در سبد خودش ۲ تا از a دارد
        $customer = Customer::factory()->create();
        $memberCart = Cart::query()->create(['token' => (string) Str::uuid(), 'customer_id' => $customer->id, 'expires_at' => now()->addDays(30)]);
        $memberCart->items()->create(['variant_id' => $a->id, 'qty' => 2]);

        // و به‌عنوان مهمان ۴ تا از a، ۱ تا از b و یکی که بعداً فروخته نمی‌شود گذاشته
        $token = $this->token($this->post('/cart/add', ['variant_id' => $a->id, 'qty' => 4]));
        $this->withCookie(CartService::COOKIE, $token)->post('/cart/add', ['variant_id' => $b->id]);
        $this->withCookie(CartService::COOKIE, $token)->post('/cart/add', ['variant_id' => $gone->id]);
        $gone->product->update(['status' => 'inactive']);

        // ورود: همین درخواست سبد مهمان را ادغام می‌کند
        $response = $this->actingAs($customer, 'customer')->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk();

        $this->assertSame(1, Cart::query()->count(), 'سبد مهمان پاک شد');
        $items = $memberCart->fresh()->items()->get()->pluck('qty', 'variant_id')->all();
        $this->assertSame([$a->id => 5, $b->id => 1], $items);   // ۲+۴ تا سقف موجودی ۵؛ جنس فروخته‌نشده نیامد
        $response->assertCookieExpired(CartService::COOKIE);

        // از دستگاه دیگر (بی کوکی) همان سبد را می‌بیند
        $this->flushSession();
        $this->actingAs($customer, 'customer')->get('/cart')->assertOk()->assertSee($a->product->tr('name'));
    }

    public function test_login_page_flow_does_not_lose_the_guest_cart(): void
    {
        $variant = $this->variant(['stock_qty' => 5]);
        $customer = Customer::factory()->create(['phone' => '0700111222', 'password' => 'Secret-123']);
        $token = $this->token($this->post('/cart/add', ['variant_id' => $variant->id, 'qty' => 2]));

        $this->withCookie(CartService::COOKIE, $token)->post('/login', ['phone' => '0700111222', 'password' => 'Secret-123'])->assertRedirect();
        $this->withCookie(CartService::COOKIE, $token)->get('/cart')->assertOk();

        $this->assertSame($customer->id, Cart::query()->firstOrFail()->customer_id);
        $this->assertSame(2, CartItem::query()->firstOrFail()->qty);
    }

    public function test_product_page_has_an_add_to_cart_form_for_the_default_variant(): void
    {
        $variant = $this->variant(['stock_qty' => 3]);

        $this->get($variant->product->url())->assertOk()
            ->assertSee('action="'.route('cart.add').'"', false)
            ->assertSee('name="variant_id" value="'.$variant->id.'"', false)
            ->assertDontSee(__('store.product.add_to_cart').' ('.__('store.header.soon').')', false);

        // ناموجود: دکمه خاموش
        $out = $this->variant(['stock_qty' => 0]);
        $this->get($out->product->url())->assertOk()->assertSee('disabled', false);
    }

    public function test_empty_cart_page_and_nav_are_friendly(): void
    {
        $this->get('/cart')->assertOk()->assertSee(__('cart.empty_heading'))->assertSee('noindex', false);
    }
}
