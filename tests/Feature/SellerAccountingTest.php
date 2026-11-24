<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\CommissionRatesRelationManager as ProductCommissionRates;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Filament\Widgets\MarketplaceFinanceStats;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductCommissionRate;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerCommissionRate;
use App\Models\SellerOrder;
use App\Models\SellerSettlement;
use App\Models\SellerTransaction;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\Commission\FinanceReport;
use App\Services\Commission\OrderAccounting;
use App\Services\Commission\SellerLedger;
use App\Services\Commission\SettlementException;
use App\Services\Orders\OrderStateMachine;
use App\Services\Payments\CashCollection;
use App\Services\Payments\CashCollectionService;
use App\Services\Settings;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۴ برنامه‌ی کلان: قاعده‌های کمیسیون، طلب هر فروشنده در سفارش چندفروشنده،
 * پیوند با پول نقد و پیک، دفتر و تسویه، برگشت، و گزارش مالی.
 * مثال‌ها از docs/COMMISSION_AND_SELLER_SETTLEMENT.md بخش ۴.
 */
class SellerAccountingTest extends TestCase
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

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function ledger(): SellerLedger
    {
        return app(SellerLedger::class);
    }

    private function variant(?Seller $seller, string|int|null $categoryRate, string $price, int $stock = 10): ProductVariant
    {
        return $this->sellableVariant($seller, $this->categoryWithRate($categoryRate), ['price' => $price, 'stock_qty' => $stock]);
    }

    /** پول کامل (دمِ در) با کد تحویل درست + «تحویل شد». */
    private function collect(Order $order, ?User $actor = null, ?string $amount = null, ?string $note = null): Payment
    {
        $order->refresh();

        return app(CashCollectionService::class)->collect($order, new CashCollection(
            deliveryCode: (string) $order->delivery_code,
            amountReceived: $amount ?? $order->total,
            note: $note,
        ), $actor ?? $this->staff());
    }

    /** مثال ۴-الف: سه فروشنده، ۶۰۰۰ (۵٪)، ۴۰۰۰ (۸٪)، ۲۰۰۰ (۳٪). */
    private function threeSellerOrder(): array
    {
        $a = $this->approvedSeller();
        $b = $this->approvedSeller();
        $c = $this->approvedSeller();
        $order = $this->placeOrder([
            [$this->variant($a, 5, '6000.00'), 1],
            [$this->variant($b, 8, '4000.00'), 1],
            [$this->variant($c, 3, '2000.00'), 1],
        ]);

        return [$order, $a, $b, $c];
    }

    private function earnings(Seller $seller): string
    {
        return Money::of((string) SellerTransaction::query()->where('seller_id', $seller->id)->where('type', 'earning')->sum('amount'));
    }

    // ───────────────────────── چندفروشنده ─────────────────────────

    public function test_one_order_with_two_sellers_gives_each_seller_its_own_earning(): void
    {
        $a = $this->approvedSeller();
        $b = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($a, 5, '6000.00'), 1], [$this->variant($b, 8, '4000.00'), 1]]);

        $this->collect($order);

        $this->assertSame('5700.00', $this->earnings($a));
        $this->assertSame('3680.00', $this->earnings($b));
        $this->assertSame(2, CommissionEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_three_sellers_match_the_documented_example_and_shipping_is_left_out(): void
    {
        [$order, $a, $b, $c] = $this->threeSellerOrder();
        $payment = $this->collect($order);

        $this->assertSame(['5700.00', '3680.00', '1940.00'], [$this->earnings($a), $this->earnings($b), $this->earnings($c)]);
        $entries = CommissionEntry::query()->where('order_id', $order->id);
        $this->assertSame('680.00', Money::of((string) (clone $entries)->sum('commission_amount')));
        $this->assertSame('11320.00', Money::of((string) (clone $entries)->sum('seller_amount')));
        // کرایه بیرون از همه‌ی اعداد بالا: پول گرفته‌شده = اجناس + کرایه
        $this->assertSame('12000.00', Money::of((string) (clone $entries)->sum('gross_amount')));
        $this->assertSame(Money::add('12000.00', $order->refresh()->delivery_fee), $payment->amount_received);
        // هر ردیف دفتر شماره‌ی یکتا و پیوند سفارش و پرداخت دارد
        $tx = SellerTransaction::query()->where('seller_id', $a->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^ST-\d{6}$/', (string) $tx->reference);
        $this->assertSame([$order->id, $payment->id], [$tx->order_id, $tx->payment_id]);
        $this->assertNotNull($tx->seller_order_id);
    }

    public function test_own_store_sales_are_kept_apart_from_seller_commission(): void
    {
        $a = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($a, 5, '6000.00'), 1], [$this->variant(null, 5, '1000.00'), 1]]);
        $this->collect($order);

        $t = app(FinanceReport::class)->totals();
        $this->assertSame('7000.00', $t['valid_sales']);
        $this->assertSame('1000.00', $t['own_sales']);
        $this->assertSame('6000.00', $t['marketplace_gmv']);
        $this->assertSame('300.00', $t['commission']);           // جنس خود بازارک کمیسیون ندارد
        $this->assertSame(1, SellerTransaction::query()->count()); // و طلب فروشنده هم ندارد
    }

    // ───────────────────────── اولویت قاعده‌ها ─────────────────────────

    public function test_product_rule_beats_category_and_regular_seller_rate(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');
        ProductCommissionRate::create(['product_id' => $variant->product_id, 'type' => 'percent', 'rate' => 2]);
        SellerCommissionRate::create(['seller_id' => $seller->id, 'rate' => 9]);

        $order = $this->placeOrder([[$variant, 2]]);
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(['product', '40.00'], [$item->rate_source, $item->commission_amount]);
    }

    public function test_category_rate_beats_regular_seller_rate_but_an_exception_beats_everything(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');
        SellerCommissionRate::create(['seller_id' => $seller->id, 'rate' => 9]);
        $first = OrderItem::query()->where('order_id', $this->placeOrder([[$variant, 1]])->id)->firstOrFail();
        $this->assertSame(['category', '50.00'], [$first->rate_source, $first->commission_amount]);

        ProductCommissionRate::create(['product_id' => $variant->product_id, 'rate' => 2]);
        SellerCommissionRate::create(['seller_id' => $seller->id, 'rate' => 0, 'is_exception' => true, 'ends_at' => now()->addMonth()]);
        $second = OrderItem::query()->where('order_id', $this->placeOrder([[$variant, 1]])->id)->firstOrFail();
        $this->assertSame(['exception', '0.00'], [$second->rate_source, $second->commission_amount]);
    }

    public function test_regular_seller_rate_applies_only_when_the_category_has_no_rate_then_the_default(): void
    {
        $seller = $this->approvedSeller();
        $noRate = $this->variant($seller, null, '1000.00');
        $this->assertSame(['default', '70.00'], $this->rateOf($noRate));   // پیش‌فرض تنظیمات ۷٪

        SellerCommissionRate::create(['seller_id' => $seller->id, 'rate' => 4]);
        $this->assertSame(['seller', '40.00'], $this->rateOf($noRate));
    }

    public function test_inactive_or_expired_rules_are_ignored(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');
        ProductCommissionRate::create(['product_id' => $variant->product_id, 'rate' => 1, 'is_active' => false]);
        ProductCommissionRate::create(['product_id' => $variant->product_id, 'rate' => 2, 'ends_at' => now()->subDay()]);
        SellerCommissionRate::create(['seller_id' => $seller->id, 'rate' => 0, 'is_exception' => true, 'is_active' => false]);

        $this->assertSame(['category', '50.00'], $this->rateOf($variant));
    }

    public function test_fixed_commission_is_per_unit_and_never_more_than_the_line(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');
        ProductCommissionRate::create(['product_id' => $variant->product_id, 'type' => 'fixed', 'fixed_amount' => 150]);

        $order = $this->placeOrder([[$variant, 3]]);
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(['fixed', '150.00', '450.00', '2550.00'], [$item->commission_type, $item->commission_fixed_amount, $item->commission_amount, $item->seller_amount]);

        $cheap = $this->variant($seller, 5, '100.00');
        ProductCommissionRate::create(['product_id' => $cheap->product_id, 'type' => 'fixed', 'fixed_amount' => 150]);
        $this->assertSame(['product', '100.00'], $this->rateOf($cheap));   // سقف = مبلغ خط
    }

    /** @return array{0: string, 1: string} [منبع، کمیسیون یک عدد] */
    private function rateOf(ProductVariant $variant): array
    {
        $item = OrderItem::query()->where('order_id', $this->placeOrder([[$variant, 1]])->id)->firstOrFail();

        return [$item->rate_source, $item->commission_amount];
    }

    // ───────────────────────── کپی تاریخی ─────────────────────────

    public function test_rate_is_frozen_at_order_time_and_later_changes_do_not_touch_old_orders(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');
        $order = $this->placeOrder([[$variant, 1]]);

        // بعد از ثبت سفارش نرخ‌ها عوض می‌شود
        $variant->product->category->update(['commission_rate' => 20]);
        ProductCommissionRate::create(['product_id' => $variant->product_id, 'rate' => 30]);
        $this->collect($order);

        $entry = CommissionEntry::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(['5.00', 'category', '50.00', '950.00'], [$entry->commission_rate, $entry->rate_source, $entry->commission_amount, $entry->seller_amount]);
        $this->assertSame('950.00', $this->earnings($seller));
    }

    // ───────────────────────── تخفیف ─────────────────────────

    public function test_seller_discount_lowers_the_commission_base_but_a_platform_discount_does_not(): void
    {
        $seller = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($seller, 5, '6000.00'), 1]]);
        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        // مثال ۴-ب: تخفیف فروشنده ۵۰۰ و سهم کوپن بازارک ۳۰۰ (کوپن هنوز ساخته نشده؛ خط را مستقیم می‌سازیم)
        $item->forceFill(['seller_discount' => '500.00', 'platform_discount' => '300.00', 'commission_amount' => '275.00', 'seller_amount' => '5225.00'])->save();

        $this->collect($order);

        $entry = CommissionEntry::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(['5500.00', '275.00', '5225.00'], [$entry->gross_amount, $entry->commission_amount, $entry->seller_amount]);
    }

    // ───────────────────────── بی‌تکراری ─────────────────────────

    public function test_posting_the_same_order_again_never_duplicates_anything(): void
    {
        [$order] = $this->threeSellerOrder();
        $this->collect($order);

        $this->assertSame(0, app(OrderAccounting::class)->postOrder($order));
        $this->assertSame(0, app(OrderAccounting::class)->postOrder($order));
        $this->assertSame(3, CommissionEntry::query()->count());
        $this->assertSame(3, SellerTransaction::query()->count());
    }

    public function test_nothing_is_posted_without_full_payment_or_before_delivery(): void
    {
        $seller = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]]);

        // کمتر از مبلغ (با دلیل) ← «بخشی پرداخت شد»؛ کمبود از سهم هیچ فروشنده‌ای کم نمی‌شود
        $this->collect($order, null, '500.00', 'مشتری فقط نیمه داشت');
        $this->assertSame('partially_paid', $order->refresh()->payment_status);
        $this->assertSame(0, SellerTransaction::query()->count());

        // پول بی تحویل: هیچ طلبی
        $other = $this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]]);
        $other->refresh();
        app(CashCollectionService::class)->collect($other, new CashCollection(
            deliveryCode: (string) $other->delivery_code, amountReceived: $other->total, markDelivered: false,
        ), $this->staff());
        $this->assertSame('paid', $other->refresh()->payment_status);
        $this->assertSame(0, SellerTransaction::query()->count());
    }

    // ───────────────────────── پول پیک ─────────────────────────

    public function test_courier_cash_keeps_earnings_pending_until_it_reaches_the_till(): void
    {
        [$order, $a] = $this->threeSellerOrder();
        $payment = $this->collect($order);

        $this->assertSame(Payment::HELD_BY_COURIER, $payment->courier_cash_status);
        $this->assertSame('5700.00', $this->ledger()->summary($a)['pending']);
        $this->assertSame('0.00', $this->ledger()->summary($a)['eligible']);

        try {
            $this->ledger()->settle($a, null, 'cash', $this->owner);
            $this->fail('پول هنوز دست پیک است');
        } catch (SettlementException $e) {
            $this->assertSame('nothing_eligible', $e->reason);
        }

        app(CashCollectionService::class)->handOver([$payment], $this->owner);

        $this->assertSame('5700.00', $this->ledger()->summary($a)['eligible']);
        // تحویل پول به صندوق پرداخت دوم نیست
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
        $t = app(FinanceReport::class)->totals();
        $this->assertSame($payment->amount_received, $t['cash_collected']);
        $this->assertSame($payment->amount_received, $t['cash_remitted']);
        $this->assertSame('0.00', $t['cash_held']);
    }

    public function test_cash_taken_at_a_branch_is_eligible_at_once_and_hold_days_delay_it(): void
    {
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        $seller = $this->approvedSeller();
        $order = $this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]], ['deliveryMethod' => 'pickup']);
        $this->collect($order);
        $this->assertSame('950.00', $this->ledger()->summary($seller)['eligible']);

        app(Settings::class)->set('marketplace.settlement_hold_days', 3);
        $later = $this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]], ['deliveryMethod' => 'pickup']);
        $this->collect($later);
        $this->assertSame('950.00', $this->ledger()->summary($seller)['pending']);

        $this->travel(3)->days();
        $this->assertSame('1900.00', $this->ledger()->summary($seller)['eligible']);
    }

    public function test_settling_during_the_hold_days_says_the_exact_date(): void
    {
        app(Settings::class)->set('delivery.pickup_enabled', true);
        app(Settings::class)->set('store.address', 'کابل، چهارراهی قمبر');
        app(Settings::class)->set('marketplace.settlement_hold_days', 7);
        $seller = $this->approvedSeller();
        $this->collect($this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]], ['deliveryMethod' => 'pickup']));

        try {
            $this->ledger()->settle($seller, null, 'cash', $this->owner);
            $this->fail('هنوز در روزهای انتظار است');
        } catch (SettlementException $e) {
            $this->assertSame('not_yet_eligible', $e->reason);
            $this->assertStringContainsString(Jalali::dateTime(now()->addDays(7)), $e->userMessage());
            $this->assertStringContainsString('۷ روز', $e->userMessage());
        }

        $this->travel(7)->days();
        $this->assertSame('950.00', (string) $this->ledger()->settle($seller, null, 'cash', $this->owner)->amount);
    }

    // ───────────────────────── تسویه ─────────────────────────

    private function eligibleSeller(): array
    {
        [$order, $a, $b, $c] = $this->threeSellerOrder();
        $payment = $this->collect($order);
        app(CashCollectionService::class)->handOver([$payment], $this->owner);

        return [$order, $a, $b, $c];
    }

    public function test_partial_then_full_settlement_and_no_earning_is_paid_twice(): void
    {
        [, $a] = $this->eligibleSeller();

        $first = $this->ledger()->settle($a, '2000', 'cash', $this->owner);
        $this->assertMatchesRegularExpression('/^SP-\d{6}$/', (string) $first->number);
        $earning = SellerTransaction::query()->where('seller_id', $a->id)->where('type', 'earning')->firstOrFail();
        $this->assertSame(['3700.00', 'partially_settled'], [$earning->open_amount, $earning->settlementStatus()]);
        $this->assertSame('3700.00', $this->ledger()->balance($a));

        $second = $this->ledger()->settle($a, null, 'bank_transfer', $this->owner, 'TRX-55');
        $this->assertSame('3700.00', $second->amount);
        $this->assertSame(['0.00', 'settled'], [$earning->refresh()->open_amount, $earning->settlementStatus()]);
        $this->assertSame('0.00', $this->ledger()->balance($a));
        $this->assertSame('5700.00', Money::add((string) $first->items()->sum('amount'), (string) $second->items()->sum('amount')));

        // سومین بار: هیچ طلبی نمانده
        $this->expectExceptionObject(new SettlementException('nothing_eligible'));
        $this->ledger()->settle($a, null, 'cash', $this->owner);
    }

    public function test_settlement_cannot_exceed_eligible_and_transfers_need_a_reference(): void
    {
        [, $a] = $this->eligibleSeller();

        foreach ([['6000', 'cash', null, 'more_than_eligible'], ['0', 'cash', null, 'amount_positive'], ['100', 'bank_transfer', '', 'reference_required']] as [$amount, $method, $ref, $reason]) {
            try {
                $this->ledger()->settle($a, $amount, $method, $this->owner, $ref);
                $this->fail($reason);
            } catch (SettlementException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
        $this->assertSame(0, SellerSettlement::query()->count());
    }

    public function test_a_second_settlement_for_the_same_money_is_refused(): void
    {
        [, $a] = $this->eligibleSeller();
        // دو درخواست پشت سر هم برای همه‌ی مبلغ (مثل دو کلیک همزمان): دومی با قفل و مانده‌ی تازه رد می‌شود
        $this->ledger()->settle($a, '5700', 'cash', $this->owner);

        try {
            $this->ledger()->settle($a, '5700', 'cash', $this->owner);
            $this->fail('دو بار پرداخت');
        } catch (SettlementException $e) {
            $this->assertSame('nothing_eligible', $e->reason);
        }
        $this->assertSame('5700.00', Money::of((string) SellerSettlement::query()->sum('amount')));
    }

    public function test_only_owner_or_manager_can_settle_or_adjust_and_amounts_are_immutable(): void
    {
        [, $a] = $this->eligibleSeller();

        foreach ([fn () => $this->ledger()->settle($a, null, 'cash', $this->staff()),
            fn () => app(CommissionService::class)->adjust($a, '100', 'دلیل', $this->staff())] as $call) {
            try {
                $call();
                $this->fail('کارمند نباید بتواند');
            } catch (SettlementException $e) {
                $this->assertSame('not_allowed', $e->reason);
            }
        }

        $tx = SellerTransaction::query()->where('seller_id', $a->id)->firstOrFail();
        $this->expectException(LogicException::class);
        $tx->forceFill(['amount' => '99999.00'])->save();
    }

    public function test_staff_cannot_open_settlement_receipts_but_the_owner_can(): void
    {
        [, $a] = $this->eligibleSeller();
        $settlement = $this->ledger()->settle($a, null, 'cash', $this->owner);

        $this->actingAs($this->staff())->get(route('admin.settlement-receipt', $settlement->uuid))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admin.settlement-receipt', $settlement->uuid))
            ->assertOk()->assertSee((string) Num::toFa((string) $settlement->number));
    }

    // ───────────────────────── لغو و برگشت جزئی ─────────────────────────

    public function test_cancelling_one_seller_before_delivery_leaves_the_others_untouched(): void
    {
        [$order, $a, $b, $c] = $this->threeSellerOrder();
        $soB = SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $b->id)->firstOrFail();
        app(OrderStateMachine::class)->transition($soB, 'cancelled', $this->owner, 'جنس تمام شده بود');

        $this->collect($order);

        $this->assertSame(['5700.00', '0.00', '1940.00'], [$this->earnings($a), $this->earnings($b), $this->earnings($c)]);
        $this->assertSame(0, CommissionEntry::query()->where('seller_order_id', $soB->id)->count());
    }

    public function test_return_after_delivery_reverses_only_that_seller_at_the_original_rate(): void
    {
        [$order, $a, $b] = $this->eligibleSeller();
        $soB = SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $b->id)->firstOrFail();

        app(OrderStateMachine::class)->transition($soB, 'returned', $this->owner, 'مشتری پس آورد');

        $reversal = CommissionEntry::query()->where('seller_order_id', $soB->id)->where('kind', CommissionEntry::KIND_REVERSAL)->firstOrFail();
        $this->assertSame(['-4000.00', '-320.00', '-3680.00', '8.00'], [$reversal->gross_amount, $reversal->commission_amount, $reversal->seller_amount, $reversal->commission_rate]);
        $this->assertSame('0.00', $this->ledger()->balance($b));
        $this->assertSame('5700.00', $this->ledger()->balance($a));            // فروشنده‌ی الف دست نخورد
        $this->assertSame('4000.00', app(FinanceReport::class)->totals()['returns']);

        // برگشت دوباره هیچ سندی نمی‌سازد
        $this->assertSame(0, app(OrderAccounting::class)->reverseSellerOrder($soB));
    }

    public function test_a_return_after_partial_settlement_is_netted_in_the_next_settlement(): void
    {
        $seller = $this->approvedSeller();
        $first = $this->placeOrder([[$this->variant($seller, 5, '1000.00'), 1]]);
        $p1 = $this->collect($first);
        $second = $this->placeOrder([[$this->variant($seller, 5, '2000.00'), 1]]);
        $p2 = $this->collect($second);
        app(CashCollectionService::class)->handOver([$p1, $p2], $this->owner);

        $this->ledger()->settle($seller, '950', 'cash', $this->owner);      // اولی تسویه شد
        $so = SellerOrder::query()->where('order_id', $first->id)->firstOrFail();
        app(OrderStateMachine::class)->transition($so, 'returned', $this->owner, 'برگشت');

        // طلب باقی: ۱۹۰۰ (دومی) − ۹۵۰ (برگشت اولی که پولش داده شده بود) = ۹۵۰
        $this->assertSame('950.00', $this->ledger()->summary($seller)['eligible']);
        $settlement = $this->ledger()->settle($seller, null, 'cash', $this->owner);
        $this->assertSame('950.00', $settlement->amount);
        $this->assertSame('0.00', $this->ledger()->balance($seller));
    }

    // ───────────────────────── گزارش ─────────────────────────

    public function test_dashboard_numbers_reconcile_with_the_records(): void
    {
        [$order] = $this->threeSellerOrder();
        $own = $this->placeOrder([[$this->variant(null, 5, '500.00'), 2]]);
        $cancelled = $this->placeOrder([[$this->variant(null, 5, '700.00'), 1]]);
        app(OrderStateMachine::class)->transition(SellerOrder::query()->where('order_id', $cancelled->id)->firstOrFail(), 'cancelled', $this->owner, 'لغو');

        $p1 = $this->collect($order);
        $p2 = $this->collect($own);
        $t = app(FinanceReport::class)->totals(...FinanceReport::range('today'));

        $this->assertSame('13000.00', $t['valid_sales']);                    // لغوشده حساب نمی‌شود
        $this->assertSame($t['marketplace_gmv'], Money::add($t['commission'], $t['seller_earnings']));
        $this->assertSame($t['valid_sales'], Money::add($t['marketplace_gmv'], $t['own_sales']));
        // پول گرفته‌شده = اجناس + کرایه‌ها
        $fees = Money::add($order->refresh()->delivery_fee, $own->refresh()->delivery_fee);
        $this->assertSame(Money::add($t['valid_sales'], $fees), $t['cash_collected']);
        $this->assertSame(Money::add($p1->amount_received, $p2->amount_received), $t['cash_held']);
        $this->assertSame('11320.00', $t['seller_pending']);
        $this->assertSame('11320.00', $t['seller_outstanding']);

        // بازه‌ی سال گذشته خالی است
        $this->assertSame('0.00', app(FinanceReport::class)->totals(now()->subYears(2), now()->subYear())['valid_sales']);
    }

    // ───────────────────────── پنل ─────────────────────────

    public function test_owner_adds_a_fixed_product_rule_from_the_product_page_but_not_for_own_products(): void
    {
        $this->actingAs($this->owner);
        $seller = $this->approvedSeller();
        $variant = $this->variant($seller, 5, '1000.00');

        Livewire::test(ProductCommissionRates::class, ['ownerRecord' => $variant->product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: ['type' => 'fixed', 'fixed_amount' => '120', 'is_active' => true])
            ->assertHasNoTableActionErrors();

        $rule = ProductCommissionRate::query()->where('product_id', $variant->product_id)->firstOrFail();
        $this->assertSame(['fixed', '120.00', '0.00'], [$rule->type, $rule->fixed_amount, $rule->rate]);
        $this->assertSame(['product', '120.00'], $this->rateOf($variant));

        $own = $this->variant(null, 5, '1000.00');
        $this->assertFalse(ProductCommissionRates::canViewForRecord($own->product, EditProduct::class));
        $this->actingAs($this->staff());
        $this->assertFalse(ProductCommissionRates::canViewForRecord($variant->product, EditProduct::class));
    }

    public function test_finance_widget_switches_period_and_shows_real_numbers(): void
    {
        [$order] = $this->threeSellerOrder();
        $this->collect($order);
        $this->actingAs($this->owner);

        Livewire::test(MarketplaceFinanceStats::class)
            ->assertOk()
            ->assertSee(Num::format('680', 0))
            ->call('setPeriod', 'year')
            ->assertSet('period', 'year')
            ->call('setPeriod', 'nonsense')
            ->assertSet('period', 'month');
    }

    public function test_partial_settlement_from_the_seller_page_with_an_error_message_when_too_much(): void
    {
        [, $a] = $this->eligibleSeller();
        $this->actingAs($this->owner);

        Livewire::test(ViewSeller::class, ['record' => $a->getRouteKey()])
            ->callAction('settle', data: ['amount' => '99999', 'method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertNotified(__('admin.finance.not_settled'));
        $this->assertSame(0, SellerSettlement::query()->count());

        Livewire::test(ViewSeller::class, ['record' => $a->getRouteKey()])
            ->callAction('settle', data: ['amount' => '1000', 'method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame('4700.00', $this->ledger()->balance($a));
    }
}
