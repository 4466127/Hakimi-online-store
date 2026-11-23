<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Models\CommissionEntry;
use App\Models\DiscountCode;
use App\Models\DiscountCodeRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerTransaction;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Commission\FinanceReport;
use App\Services\Marketing\DiscountCodeException;
use App\Services\Marketing\DiscountCodeService;
use App\Services\Orders\OrderStateMachine;
use App\Services\Payments\CashCollection;
use App\Services\Payments\CashCollectionService;
use App\Support\Money;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۹-۳: کد تخفیف — بررسی، مبلغ، سقف‌ها، خرید اول، صفحه‌ی تسویه، پخش بین فروشنده‌ها،
 * تطبیق مالی، لغو و برگشت، و «مدیریت کدهای تخفیف» در پنل.
 */
class DiscountCodeTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
        $this->owner = User::factory()->owner()->create();
    }

    // ───────────────────────── کمک‌ها ─────────────────────────

    private function code(array $attributes = []): DiscountCode
    {
        return DiscountCode::query()->create($attributes + [
            'code' => 'EID1405',
            'type' => DiscountCode::TYPE_PERCENT,
            'value' => '10',
            'is_active' => true,
        ]);
    }

    private function service(): DiscountCodeService
    {
        return app(DiscountCodeService::class);
    }

    private function variant(?Seller $seller, string|int|null $rate, string $price, int $stock = 20): ProductVariant
    {
        return $this->sellableVariant($seller, $this->categoryWithRate($rate), ['price' => $price, 'stock_qty' => $stock]);
    }

    private function reason(callable $fn): string
    {
        try {
            $fn();
        } catch (DiscountCodeException $e) {
            return $e->discountReason;
        }
        $this->fail('کد تخفیف باید رد می‌شد.');
    }

    private function collect(Order $order): Payment
    {
        $order->refresh();

        return app(CashCollectionService::class)->collect($order, new CashCollection(
            deliveryCode: (string) $order->delivery_code,
            amountReceived: $order->total,
        ), $this->owner);
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

    private function guestCart(array $lines): void
    {
        $this->withCookie(CartService::COOKIE, $this->cartWith($lines)->token);
    }

    // ───────────────────────── بررسی و مبلغ ─────────────────────────

    public function test_invalid_inactive_unstarted_expired_exhausted_and_small_orders_are_refused_with_dari_reasons(): void
    {
        $this->assertSame('invalid', $this->reason(fn () => $this->service()->quote('NOPE', '1000.00')));
        $this->assertSame('empty', $this->reason(fn () => $this->service()->quote('  ', '1000.00')));

        $this->code(['code' => 'OFF', 'is_active' => false]);
        $this->assertSame('invalid', $this->reason(fn () => $this->service()->quote('off', '1000.00')));

        $this->code(['code' => 'SOON', 'starts_at' => now()->addDay()]);
        $this->assertSame('not_started', $this->reason(fn () => $this->service()->quote('SOON', '1000.00')));

        $this->code(['code' => 'OLD', 'ends_at' => now()->subMinute()]);
        $this->assertSame('expired', $this->reason(fn () => $this->service()->quote('OLD', '1000.00')));

        $full = $this->code(['code' => 'FULL', 'max_uses' => 2]);
        DB::table('discount_codes')->where('id', $full->id)->update(['used_count' => 2]);
        $this->assertSame('exhausted', $this->reason(fn () => $this->service()->quote('FULL', '1000.00')));

        $this->code(['code' => 'BIG', 'min_order_total' => '2000']);
        $this->assertSame('min_order', $this->reason(fn () => $this->service()->quote('BIG', '1999.99')));
        $this->assertSame('200.00', $this->service()->quote('BIG', '2000.00')['amount']);

        // پیام دری، بی «کوپن»
        $message = (new DiscountCodeException('min_order', ['amount' => '۲٬۰۰۰ افغانی']))->userMessage();
        $this->assertStringContainsString('کد تخفیف', $message);
        $this->assertStringContainsString('۲٬۰۰۰', $message);
    }

    public function test_percent_discount_respects_the_cap_and_fixed_discount_never_exceeds_the_goods(): void
    {
        $this->code(['code' => 'TEN', 'value' => '10']);
        $this->code(['code' => 'CAPPED', 'value' => '20', 'max_discount' => '300']);
        $this->code(['code' => 'FLAT', 'type' => DiscountCode::TYPE_FIXED, 'value' => '250']);

        $this->assertSame('123.45', $this->service()->quote('ten', '1234.50')['amount']);
        $this->assertSame('300.00', $this->service()->quote('CAPPED', '5000.00')['amount']);   // ۲۰٪ = ۱۰۰۰ ← سقف ۳۰۰
        $this->assertSame('200.00', $this->service()->quote('CAPPED', '1000.00')['amount']);
        $this->assertSame('250.00', $this->service()->quote('FLAT', '1000.00')['amount']);
        $this->assertSame('180.00', $this->service()->quote('FLAT', '180.00')['amount']);      // هرگز بیشتر از اجناس
        // عدد فارسی و حروف کوچک و فاصله
        $this->code(['code' => 'NOROZ1405', 'value' => '5']);
        $this->assertSame('50.00', $this->service()->quote(' noroz۱۴۰۵ ', '1000.00')['amount']);
    }

    public function test_allocation_is_proportional_and_adds_up_exactly(): void
    {
        $this->assertSame(['33.34', '33.33', '33.33'], array_values($this->service()->allocate('100.00', ['10.00', '10.00', '10.00'])));
        $shares = $this->service()->allocate('500.00', ['6000.00', '4000.00', '2000.00', '1000.00']);
        $this->assertSame(['230.77', '153.85', '76.92', '38.46'], array_values($shares));
        $this->assertSame('500.00', array_reduce($shares, fn ($c, $s) => Money::add($c, $s), '0.00'));
        $this->assertSame(['0.00', '0.00'], array_values($this->service()->allocate('0.00', ['1.00', '2.00'])));
    }

    // ───────────────────────── صفحه‌ی تسویه (مهمان) ─────────────────────────

    public function test_guest_applies_a_code_sees_the_breakdown_and_places_a_discounted_order(): void
    {
        $this->code(['code' => 'EID1405', 'value' => '10']);
        $this->guestCart([[$this->variant(null, null, '1500.00'), 2]]);

        $this->get('/checkout')->assertOk()
            ->assertSee(__('checkout.discount.label'))
            ->assertSee(__('checkout.discount.apply'))
            ->assertDontSee('کوپن');

        $this->post('/checkout/discount', $this->form(['discount_code' => 'eid1405']))
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHas(CheckoutController::DISCOUNT_SESSION_KEY, 'EID1405')
            ->assertSessionHasInput('purchaser_name', 'احمد حکیمی');

        $this->get('/checkout')->assertOk()
            ->assertSee(__('checkout.discount.row_with_code', ['code' => 'EID1405']))
            ->assertSee('۳٬۰۰۰')            // قیمت اجناس
            ->assertSee('۳۰۰')              // تخفیف
            ->assertSee('۲٬۷۰۰')            // بعد از تخفیف
            ->assertSee('۲٬۹۰۰');           // + کرایه‌ی ۲۰۰ (جدا)

        // مرورگر نمی‌تواند مبلغ تخفیف بفرستد
        $this->post('/checkout', $this->form(['discount_total' => '2999', 'discount_amount' => '2999']))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertNull($order->customer_id);
        $this->assertSame('3000.00', $order->subtotal);
        $this->assertSame('300.00', $order->discount_total);
        $this->assertSame('EID1405', $order->discount_code);
        $this->assertSame('200.00', $order->delivery_fee);
        $this->assertSame('2900.00', $order->total);
        $this->assertSame('2900.00', Payment::query()->where('order_id', $order->id)->value('amount'));
        $this->assertSame(1, DiscountCode::query()->value('used_count'));
        $redemption = DiscountCodeRedemption::query()->sole();
        $this->assertSame('0700111222', $redemption->phone);
        $this->assertSame('300.00', $redemption->amount);
        $this->assertNull(session(CheckoutController::DISCOUNT_SESSION_KEY));

        $this->get(route('order.show', ['uuid' => $order->uuid]))->assertOk()
            ->assertSee(__('checkout.discount.row_with_code', ['code' => 'EID1405']));
    }

    public function test_wrong_code_shows_an_error_and_keeps_the_form(): void
    {
        $this->guestCart([[$this->variant(null, null, '1500.00'), 1]]);

        $this->post('/checkout/discount', $this->form(['discount_code' => 'WRONG']))
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHasErrorsIn('discount', ['discount_code' => __('checkout.discount.errors.invalid')])
            ->assertSessionHasInput('address_line', 'کارته‌ی چهار، سرک اول')
            ->assertSessionMissing(CheckoutController::DISCOUNT_SESSION_KEY);
    }

    public function test_order_with_no_applied_code_has_no_discount(): void
    {
        $this->code();
        $this->guestCart([[$this->variant(null, null, '1500.00'), 1]]);

        $this->post('/checkout', $this->form(['discount_code' => 'EID1405']))->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame('0.00', $order->discount_total);
        $this->assertSame(0, DiscountCode::query()->value('used_count'));
    }

    // ───────────────────────── سقف‌ها و خرید اول ─────────────────────────

    public function test_per_phone_limit_blocks_a_second_order_and_the_order_is_not_placed(): void
    {
        $this->code(['max_uses_per_phone' => 1]);
        $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);

        // همان شماره، از صفحه‌ی تسویه: سفارش ثبت نمی‌شود، کد برداشته می‌شود، پیام روشن
        $this->guestCart([[$this->variant(null, null, '1000.00'), 1]]);
        $this->withSession([CheckoutController::DISCOUNT_SESSION_KEY => 'EID1405'])
            ->post('/checkout', $this->form())
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHas('error', __('checkout.discount.not_placed', ['reason' => __('checkout.discount.errors.per_phone')]))
            ->assertSessionMissing(CheckoutController::DISCOUNT_SESSION_KEY);
        $this->assertSame(1, Order::query()->count());

        // شماره‌ی دیگر می‌تواند
        $other = $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405', 'purchaserPhone' => '0799000111', 'recipientPhone' => '0799000111']);
        $this->assertSame('100.00', $other->discount_total);
        $this->assertSame(2, DiscountCode::query()->value('used_count'));
    }

    public function test_total_usage_limit_is_enforced_under_a_row_lock(): void
    {
        $this->code(['max_uses' => 1]);
        $sql = [];
        DB::listen(function (QueryExecuted $q) use (&$sql): void {
            $sql[] = strtolower($q->sql);
        });

        $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);
        $this->assertTrue(collect($sql)->contains(fn (string $s) => str_contains($s, 'from `discount_codes`') && str_contains($s, 'for update')),
            'ردیف کد باید هنگام ثبت سفارش قفل شود');

        $this->assertSame('exhausted', $this->reason(fn () => $this->placeOrder(
            [[$this->variant(null, null, '1000.00'), 1]],
            ['discountCode' => 'EID1405', 'purchaserPhone' => '0799000222', 'recipientPhone' => '0799000222'],
        )));
        $this->assertSame(1, Order::query()->count());                  // سفارش دوم اصلاً ساخته نشد (rollback)
        $this->assertSame(1, DiscountCode::query()->value('used_count'));
    }

    public function test_an_order_can_redeem_only_once(): void
    {
        $code = $this->code();
        $order = $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);

        $this->expectException(QueryException::class);
        $this->service()->redeem($order, $code, '10.00', '0700111222', null);
    }

    public function test_first_purchase_code_ignores_cancelled_and_unpaid_orders_but_not_a_completed_one(): void
    {
        $this->code(['first_order_only' => true]);

        $cancelled = $this->placeOrder([[$this->variant(null, null, '500.00'), 1]]);
        app(OrderStateMachine::class)->transition(SellerOrder::query()->where('order_id', $cancelled->id)->firstOrFail(), 'cancelled', $this->owner, 'لغو');
        $this->placeOrder([[$this->variant(null, null, '500.00'), 1]]);           // پول‌نگرفته
        $this->assertSame('50.00', $this->service()->quote('EID1405', '500.00', '0700111222')['amount']);

        $done = $this->placeOrder([[$this->variant(null, null, '500.00'), 1]]);
        $this->collect($done);
        $this->assertSame('first_order', $this->reason(fn () => $this->service()->quote('EID1405', '500.00', '0700111222')));
        // شماره‌ی تازه هنوز خرید اول است
        $this->assertSame('50.00', $this->service()->quote('EID1405', '500.00', '0799000333')['amount']);
    }

    // ───────────────────────── پول و حساب ─────────────────────────

    public function test_multi_vendor_discount_is_paid_by_bazarak_and_reconciles(): void
    {
        $this->code(['type' => DiscountCode::TYPE_FIXED, 'value' => '650']);
        $a = $this->approvedSeller();
        $b = $this->approvedSeller();
        $order = $this->placeOrder([
            [$this->variant($a, 5, '6000.00'), 1],
            [$this->variant($b, 8, '4000.00'), 1],
            [$this->variant(null, null, '3000.00'), 1],          // جنس خود بازارک
        ], ['discountCode' => 'EID1405']);

        // پخش ۶۵۰ به نسبت ۶۰۰۰/۴۰۰۰/۳۰۰۰
        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(['300.00', '200.00', '150.00'], $items->pluck('platform_discount')->all());
        // کمیسیون و سهم فروشنده دست نخورده
        $this->assertSame(['300.00', '320.00', '0.00'], $items->pluck('commission_amount')->all());
        $this->assertSame(['5700.00', '3680.00', '0.00'], $items->pluck('seller_amount')->all());

        $soA = SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $a->id)->sole();
        $this->assertSame('300.00', $soA->platform_discount_total);
        $this->assertSame('5700.00', $soA->customer_paid_products);
        $this->assertSame('6000.00', $soA->commissionable_amount);
        $this->assertSame('5700.00', $soA->seller_payable);
        $this->assertSame('12550.00', $order->total);                   // ۱۳۰۰۰ − ۶۵۰ + ۲۰۰

        $this->collect($order);

        $this->assertSame('5700.00', Money::of((string) SellerTransaction::query()->where('seller_id', $a->id)->sum('amount')));
        $this->assertSame('3680.00', Money::of((string) SellerTransaction::query()->where('seller_id', $b->id)->sum('amount')));

        $t = app(FinanceReport::class)->totals(...FinanceReport::range('today'));
        $this->assertSame('650.00', $t['platform_discounts']);
        $this->assertSame('620.00', $t['commission']);                  // کمیسیون کم نشد
        $this->assertSame('13000.00', $t['valid_sales']);
        // پول گرفته‌شده = فروش − تخفیف بازارک + کرایه
        $this->assertSame(Money::add(Money::sub($t['valid_sales'], $t['platform_discounts']), '200.00'), $t['cash_collected']);
        // اجناس فروشندگان: مشتری داد + بازارک داد = سهم فروشنده + کمیسیون
        $customerPaid = Money::sub($t['marketplace_gmv'], '500.00');
        $this->assertSame(Money::add($t['seller_earnings'], $t['commission']), Money::add($customerPaid, '500.00'));
    }

    public function test_cancelling_the_whole_order_releases_the_code_but_keeps_history(): void
    {
        $this->code(['max_uses' => 1, 'max_uses_per_phone' => 1]);
        $order = $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);
        $this->assertSame(1, DiscountCode::query()->value('used_count'));

        app(OrderStateMachine::class)->transition(SellerOrder::query()->where('order_id', $order->id)->sole(), 'cancelled', $this->owner, 'مشتری نخواست');

        $this->assertSame(0, DiscountCode::query()->value('used_count'));
        $redemption = DiscountCodeRedemption::query()->sole();
        $this->assertSame(DiscountCodeRedemption::RELEASED, $redemption->status);
        $this->assertSame('100.00', $redemption->amount);              // تاریخچه می‌ماند
        $this->assertNotNull($redemption->released_at);

        // دوباره قابل استفاده (همان شماره)
        $again = $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);
        $this->assertSame('100.00', $again->discount_total);
    }

    public function test_cancelling_one_seller_keeps_the_code_used_and_drops_only_that_share(): void
    {
        $this->code(['type' => DiscountCode::TYPE_FIXED, 'value' => '500']);
        $a = $this->approvedSeller();
        $b = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($a, 5, '6000.00'), 1], [$this->variant($b, 8, '4000.00'), 1]], ['discountCode' => 'EID1405']);

        app(OrderStateMachine::class)->transition(SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $b->id)->sole(), 'cancelled', $this->owner, 'تمام شد');

        $order->refresh();
        $this->assertSame('6000.00', $order->subtotal);
        $this->assertSame('300.00', $order->discount_total);           // سهم فروشنده‌ی الف
        $this->assertSame('5900.00', $order->total);
        $this->assertSame(1, DiscountCode::query()->value('used_count'));
        $this->assertSame(DiscountCodeRedemption::ACTIVE, DiscountCodeRedemption::query()->value('status'));
    }

    public function test_partial_return_reverses_only_that_share_and_keeps_the_code_used(): void
    {
        $this->code(['type' => DiscountCode::TYPE_FIXED, 'value' => '500']);
        $a = $this->approvedSeller();
        $b = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($a, 5, '6000.00'), 1], [$this->variant($b, 8, '4000.00'), 1]], ['discountCode' => 'EID1405']);
        $this->collect($order);

        app(OrderStateMachine::class)->transition(SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $b->id)->sole(), 'returned', $this->owner, 'مشتری پس آورد');

        $reversal = CommissionEntry::query()->where('kind', CommissionEntry::KIND_REVERSAL)->sole();
        $this->assertSame('-200.00', $reversal->platform_discount);
        $redemption = DiscountCodeRedemption::query()->sole();
        $this->assertSame('200.00', $redemption->returned_amount);
        $this->assertSame(DiscountCodeRedemption::ACTIVE, $redemption->status);
        $this->assertSame(1, DiscountCode::query()->value('used_count'));

        $t = app(FinanceReport::class)->totals(...FinanceReport::range('today'));
        $this->assertSame('300.00', $t['platform_discounts']);         // ۵۰۰ − سهم برگشتی ۲۰۰
        $this->assertSame('5700.00', Money::of((string) SellerTransaction::query()->where('seller_id', $a->id)->sum('amount')));
        $this->assertSame('0.00', Money::of((string) SellerTransaction::query()->where('seller_id', $b->id)->sum('amount')));
    }

    // ───────────────────────── پنل ─────────────────────────

    public function test_manager_creates_and_edits_a_code_and_staff_cannot_see_the_section(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        Livewire::test(CreateDiscountCode::class)
            ->fillForm([
                'code' => 'yalda 1405',
                'type' => DiscountCode::TYPE_PERCENT,
                'value' => '15',
                'max_discount' => '400',
                'min_order_total' => '1000',
                'max_uses' => '50',
                'max_uses_per_phone' => '1',
                'first_order_only' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $code = DiscountCode::query()->sole();
        $this->assertSame('YALDA1405', $code->code);
        $this->assertSame('15.00', $code->value);
        $this->assertTrue($code->first_order_only);

        Livewire::test(EditDiscountCode::class, ['record' => $code->getRouteKey()])
            ->fillForm(['type' => DiscountCode::TYPE_FIXED, 'value' => '200'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($code->refresh()->max_discount);              // سقف فقط برای درصدی

        Livewire::test(ListDiscountCodes::class)
            ->assertSee(__('admin.discounts.nav'))
            ->assertSee('YALDA1405')
            ->assertDontSee('کوپن');

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get(DiscountCodeResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_used_code_cannot_be_deleted(): void
    {
        $used = $this->code();
        $unused = $this->code(['code' => 'UNUSED']);
        $this->placeOrder([[$this->variant(null, null, '1000.00'), 1]], ['discountCode' => 'EID1405']);

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->assertFalse($manager->can('delete', $used));
        $this->assertTrue($manager->can('delete', $unused));
    }

    public function test_no_dari_screen_says_coupon(): void
    {
        foreach (glob(lang_path('fa').'/*.php') as $file) {
            $this->assertStringNotContainsString('کوپن', (string) file_get_contents($file), basename($file));
        }
    }
}
