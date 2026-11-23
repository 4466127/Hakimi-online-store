<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CollectionPoint;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Province;
use App\Models\SellerOrder;
use App\Models\StockMovement;
use App\Services\Orders\CheckoutException;
use App\Services\Orders\CheckoutService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsOrders;
use Tests\TestCase;

/**
 * مرحله‌ی ۲-۱: ثبت سفارش چندفروشنده از سبد.
 * (جزئیات مالی کامل‌تر و ۱۷ تست کمیسیون: مرحله‌ی ۴؛ این‌جا صورت مالی ثبت‌شده‌ی هر فروشنده و ثابت‌ماندن نرخ.)
 */
class CheckoutServiceTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOrderBasics();
    }

    private function expectReason(string $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('انتظار خطای '.$reason.' بود.');
        } catch (CheckoutException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertNotSame('', $e->userMessage());
        }
    }

    private function assertNothingCreated(): void
    {
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, SellerOrder::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_single_seller_cash_on_delivery_order_is_created_with_exact_totals(): void
    {
        $seller = $this->approvedSeller();
        $variant = $this->sellableVariant($seller, $this->categoryWithRate(5), ['price' => '1500.00', 'compare_at_price' => '2000.00', 'stock_qty' => 10]);
        $cart = $this->cartWith([[$variant, 2]]);

        $order = app(CheckoutService::class)->place($cart, $this->checkoutData());

        // شماره‌ی سفارش فقط عدد است (خواست صاحب پروژه، ۲ قوس ۱۴۰۵) و کد تحویل پنج‌رقمی دارد
        $this->assertSame(Order::numberFor($order->id), $order->number);
        $this->assertMatchesRegularExpression('/^\d{9}$/', (string) $order->number);
        $this->assertMatchesRegularExpression('/^\d{5}$/', (string) $order->delivery_code);
        $this->assertSame('pending', $order->status);
        $this->assertSame('awaiting_collection', $order->payment_status);
        $this->assertSame('cod', $order->payment_method_code);
        $this->assertSame('3000.00', $order->subtotal);                    // ۲ × ۱۵۰۰
        $this->assertSame('200.00', $order->delivery_fee);                 // زودترین روز، بار سبک
        $this->assertSame('3200.00', $order->total);
        $this->assertSame('0.00', $order->paid_total);
        $this->assertSame('consolidated', $order->delivery_mode);
        $this->assertSame(1000, $order->delivery_weight_grams);            // ۲ × وزن پیش‌فرض ۵۰۰ گرام

        $this->assertCount(1, $order->sellerOrders);
        $so = $order->sellerOrders[0];
        $this->assertSame($order->number.'-1', $so->number);
        $this->assertSame($seller->id, $so->seller_id);
        $this->assertSame('3000.00', $so->gross_subtotal);
        $this->assertSame('150.00', $so->commission_total);                // ۵٪
        $this->assertSame('2850.00', $so->seller_payable);

        $item = $so->items[0];
        $this->assertSame(2, $item->qty);
        $this->assertSame('1500.00', $item->unit_price);
        $this->assertSame('3000.00', $item->line_total);
        $this->assertSame('1000.00', $item->discount_amount);              // سود نمایشی تخفیف
        $this->assertSame('category', $item->rate_source);

        // پرداخت منتظر گرفتن پول، موجودی رزرو، سبد خالی، تاریخچه
        $payment = $order->payments[0];
        $this->assertSame('awaiting_collection', $payment->status);
        $this->assertSame('3200.00', $payment->amount);
        $this->assertSame('2.000', $variant->fresh()->reserved_qty);
        $this->assertSame('10.000', $variant->fresh()->stock_qty);
        $this->assertSame(0, Cart::query()->find($cart->id)->items()->count());
        $movement = StockMovement::query()->firstOrFail();
        $this->assertSame('reserve', $movement->type);
        $this->assertSame('2.000', $movement->qty_delta);
        $this->assertSame(3, OrderStatusHistory::query()->count());        // order + seller_order + payment
    }

    public function test_three_sellers_split_into_three_seller_orders_with_independent_commission(): void
    {
        // مثال صاحب پروژه: A ۶٬۰۰۰ با ۵٪، B ۴٬۰۰۰ با ۸٪، C ۲٬۰۰۰ با ۳٪ ← کمیسیون ۶۸۰، طلب فروشنده‌ها ۱۱٬۳۲۰
        $a = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(5), ['price' => '3000.00']);
        $b = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(8), ['price' => '4000.00']);
        $c = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(3), ['price' => '2000.00']);

        $order = $this->placeOrder([[$a, 2], [$b, 1], [$c, 1]]);

        $this->assertSame('12000.00', $order->subtotal);
        $this->assertCount(3, $order->sellerOrders);
        $byNumber = $order->sellerOrders->keyBy('number');

        $first = $byNumber[$order->number.'-1'];
        $second = $byNumber[$order->number.'-2'];
        $third = $byNumber[$order->number.'-3'];
        $this->assertSame(['6000.00', '300.00', '5700.00'], [$first->gross_subtotal, $first->commission_total, $first->seller_payable]);
        $this->assertSame(['4000.00', '320.00', '3680.00'], [$second->gross_subtotal, $second->commission_total, $second->seller_payable]);
        $this->assertSame(['2000.00', '60.00', '1940.00'], [$third->gross_subtotal, $third->commission_total, $third->seller_payable]);

        $this->assertSame('680.00', $order->sellerOrders->reduce(fn (string $c, SellerOrder $s) => bcadd($c, $s->commission_total, 2), '0.00'));
        $this->assertSame('11320.00', $order->sellerOrders->reduce(fn (string $c, SellerOrder $s) => bcadd($c, $s->seller_payable, 2), '0.00'));
        foreach ($order->sellerOrders as $so) {
            $this->assertSame('pending', $so->status);
            $this->assertSame('0.00', $so->delivery_fee, 'تحویل یکپارچه: کرایه روی سفارش والد است');
            $this->assertSame($so->gross_subtotal, $so->customer_paid_products);
        }
        // یک پرداخت برای کل سفارش (کرایه ۲۰۰ هم هست)
        $this->assertSame('12200.00', $order->total);
        $this->assertSame('12200.00', $order->payments[0]->amount);
        $this->assertCount(1, $order->payments);
    }

    public function test_platform_own_items_get_their_own_seller_order_with_no_commission(): void
    {
        $own = $this->sellableVariant(null, null, ['price' => '900.00']);
        $theirs = $this->sellableVariant($this->approvedSeller(), $this->categoryWithRate(10), ['price' => '1000.00']);

        $order = $this->placeOrder([[$own, 1], [$theirs, 1]]);

        $this->assertCount(2, $order->sellerOrders);
        $ownOrder = $order->sellerOrders->firstWhere('seller_id', null);
        $this->assertNotNull($ownOrder);
        $this->assertSame('0.00', $ownOrder->commission_total);
        $this->assertSame('0.00', $ownOrder->seller_payable, 'جنس خود بازارک طلب فروشنده ندارد');
        $this->assertSame('platform', $ownOrder->items[0]->rate_source);
        $this->assertSame('900.00', $ownOrder->gross_subtotal);
    }

    public function test_commission_rate_is_frozen_on_the_item_at_order_time(): void
    {
        $category = $this->categoryWithRate(5);
        $variant = $this->sellableVariant($this->approvedSeller(), $category, ['price' => '1000.00']);

        $order = $this->placeOrder([[$variant, 1]]);
        $category->update(['commission_rate' => 20]);                      // بعد از سفارش نرخ بالا رفت
        $variant->update(['price' => '5000.00']);                          // و قیمت هم

        $item = OrderItem::query()->firstOrFail();
        $this->assertSame('5.0000', $item->commission_rate);
        $this->assertSame('50.00', $item->commission_amount);
        $this->assertSame('950.00', $item->seller_amount);
        $this->assertSame('1000.00', $item->unit_price);
        $this->assertSame('50.00', $order->fresh()->sellerOrders[0]->commission_total);
    }

    public function test_purchaser_and_recipient_can_be_different_people(): void
    {
        $variant = $this->sellableVariant(null);

        $order = $this->placeOrder([[$variant, 1]], [
            'purchaserName' => 'کریم از خارج', 'purchaserPhone' => '0700999888', 'purchaserEmail' => 'karim@example.com',
            'purchaserCountry' => 'DE', 'isGift' => true,
            'recipientName' => 'مادرم', 'recipientPhone' => '0700555444',
        ]);

        $this->assertSame('کریم از خارج', $order->purchaser_name);
        $this->assertSame('DE', $order->purchaser_country);
        $this->assertTrue($order->is_gift);
        $this->assertSame('مادرم', $order->recipient_name);
        $this->assertSame('0700555444', $order->recipient_phone);
    }

    public function test_insufficient_stock_creates_nothing(): void
    {
        $variant = $this->sellableVariant(null, null, ['stock_qty' => 1]);
        $cart = $this->cartWith([[$variant, 1]]);
        $variant->update(['stock_qty' => 0]);                              // بعد از گذاشتن در سبد، تمام شد

        $this->expectReason('out_of_stock', fn () => app(CheckoutService::class)->place($cart, $this->checkoutData()));

        $this->assertNothingCreated();
        $this->assertSame(1, $cart->items()->count(), 'سبد دست نخورد');
    }

    public function test_two_buyers_for_the_last_item_only_one_succeeds(): void
    {
        $variant = $this->sellableVariant(null, null, ['stock_qty' => 1]);
        $cartOne = $this->cartWith([[$variant, 1]]);
        $cartTwo = $this->cartWith([[$variant, 1]]);

        app(CheckoutService::class)->place($cartOne, $this->checkoutData());
        $this->expectReason('out_of_stock', fn () => app(CheckoutService::class)->place($cartTwo, $this->checkoutData()));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame('1.000', $variant->fresh()->reserved_qty);
    }

    public function test_backorder_item_is_accepted_and_flagged_for_stock_check(): void
    {
        $variant = $this->sellableVariant(null, null, ['stock_qty' => 1, 'allow_backorder' => true]);

        $order = $this->placeOrder([[$variant, 3]]);

        $this->assertTrue($order->needs_stock_check);
        $this->assertSame('3.000', $variant->fresh()->reserved_qty);
    }

    public function test_unsellable_items_block_the_order(): void
    {
        $inactive = $this->sellableVariant(null, null, ['is_active' => false]);
        $this->expectReason('cart_problems', fn () => $this->placeOrder([[$inactive, 1]]));

        $draft = Product::factory()->withVariant()->create()->variants()->firstOrFail();   // پیش‌نویس: فروخته نمی‌شود
        $this->expectReason('cart_problems', fn () => $this->placeOrder([[$draft, 1]]));

        $this->expectReason('empty_cart', fn () => app(CheckoutService::class)->place($this->cartWith([]), $this->checkoutData()));
        $this->assertNothingCreated();
    }

    public function test_pickup_order_has_no_delivery_fee_no_address_and_uses_cash_at_the_point(): void
    {
        app(Settings::class)->set('store.address', 'کابل، چهارراهی صدارت');
        $variant = $this->sellableVariant(null, null, ['price' => '1000.00']);
        $point = CollectionPoint::query()->firstOrFail();

        $order = $this->placeOrder([[$variant, 1]], ['deliveryMethod' => 'pickup', 'collectionPointId' => $point->id]);

        $this->assertTrue($order->isPickup());
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('1000.00', $order->total);
        $this->assertNull($order->address_line);
        // حضوری هم روز دارد (جنس تا آن روز در نمایندگی آماده می‌شود) ولی کرایه ندارد
        $this->assertSame($this->firstPickupDate(), $order->delivery_date->format('Y-m-d'));
        $this->assertSame($point->id, $order->collection_point_id);
        $this->assertSame('cash_point', $order->payment_method_code);
        $this->assertSame('awaiting_collection', $order->payment_status);
    }

    public function test_payment_method_must_fit_the_delivery_method_and_be_active(): void
    {
        app(Settings::class)->set('store.address', 'کابل، چهارراهی صدارت');
        $variant = $this->sellableVariant(null);

        $this->expectReason('payment_not_for_delivery', fn () => $this->placeOrder([[$variant, 1]], ['paymentMethodCode' => 'cash_point']));
        $this->expectReason('payment_not_for_delivery', fn () => $this->placeOrder([[$variant, 1]], ['deliveryMethod' => 'pickup', 'paymentMethodCode' => 'cod']));
        $this->expectReason('payment_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['paymentMethodCode' => 'hesabpay']));   // غیرفعال و درگاه هنوز نیست
        $this->expectReason('payment_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['paymentMethodCode' => 'nonsense']));

        PaymentMethod::query()->where('code', 'cod')->update(['is_active' => false]);
        $this->expectReason('payment_unavailable', fn () => $this->placeOrder([[$variant, 1]]));
        $this->assertNothingCreated();
    }

    public function test_payment_amount_limits_are_enforced_on_the_server(): void
    {
        $variant = $this->sellableVariant(null, null, ['price' => '500.00']);
        PaymentMethod::query()->where('code', 'cod')->update(['min_total' => '1000.00']);

        $this->expectReason('payment_unavailable', fn () => $this->placeOrder([[$variant, 1]]));   // ۵۰۰ + ۲۰۰ کرایه = ۷۰۰ < ۱۰۰۰
        $this->assertNothingCreated();
    }

    public function test_delivery_checks_address_province_date_and_weight(): void
    {
        $variant = $this->sellableVariant(null);

        $this->expectReason('address_required', fn () => $this->placeOrder([[$variant, 1]], ['addressLine' => '  ']));
        $this->expectReason('delivery_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['provinceId' => Province::query()->where('code', 'HER')->value('id')]));
        $this->expectReason('delivery_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['provinceId' => 999999]));
        $this->expectReason('delivery_date_invalid', fn () => $this->placeOrder([[$variant, 1]], ['deliveryDate' => '2001-01-01']));
        $this->expectReason('delivery_date_invalid', fn () => $this->placeOrder([[$variant, 1]], ['deliveryDate' => null]));

        $heavy = $this->sellableVariant(null, null, ['weight_grams' => 40000]);
        DeliveryZone::query()->update(['max_weight_grams' => 30000]);
        $this->expectReason('delivery_too_heavy', fn () => $this->placeOrder([[$heavy, 1]]));

        $this->assertNothingCreated();
    }

    public function test_pickup_needs_the_store_address_and_a_valid_active_point(): void
    {
        $variant = $this->sellableVariant(null);
        $this->expectReason('pickup_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['deliveryMethod' => 'pickup']));   // آدرس فروشگاه خالی

        app(Settings::class)->set('store.address', 'کابل، چهارراهی صدارت');
        $this->expectReason('collection_point_invalid', fn () => $this->placeOrder([[$variant, 1]], ['deliveryMethod' => 'pickup', 'collectionPointId' => 999999]));

        CollectionPoint::query()->update(['is_active' => false]);
        $this->expectReason('pickup_unavailable', fn () => $this->placeOrder([[$variant, 1]], ['deliveryMethod' => 'pickup']));
        $this->assertNothingCreated();
    }
}
