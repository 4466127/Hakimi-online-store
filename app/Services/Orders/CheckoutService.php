<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Cart;
use App\Models\CollectionPoint;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Services\ActivityLogger;
use App\Services\Cart\CartService;
use App\Services\Catalog\PricingService;
use App\Services\Catalog\ProductCatalog;
use App\Services\Commission\AppliedRate;
use App\Services\Commission\CommissionService;
use App\Services\Delivery\DeliveryCalculator;
use App\Services\Payments\Gateways\PaymentGatewayManager;
use App\Services\Settings;
use App\Services\StoreInfo;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ثبت سفارش از سبد (برنامه‌ی کلان، مرحله‌ی ۲).
 *
 * یک سفارش والد + یک «سفارش فروشنده» برای هر فروشنده (اجناس خود بازارک هم یک گروه جدا).
 * همه‌چیز داخل **یک transaction** با قفل ردیف نوع‌ها (به ترتیب شناسه) انجام می‌شود:
 *   - هیچ مبلغی از مرورگر پذیرفته نمی‌شود: قیمت، موجودی، کرایه و جمع از نو در سرور حساب می‌شود.
 *   - موجودی رزرو می‌شود (دو نفر همزمان آخرین قلم را نمی‌خرند)؛ جنس «فروش بی موجودی» رزرو می‌شود ولی
 *     سفارش `needs_stock_check` می‌گیرد.
 *   - **قاعده‌ی کمیسیون همان لحظه روی هر خط کپی و ثابت می‌شود** (تغییر بعدی نرخ روی سفارش اثر ندارد).
 *   - صورت مالی هر فروشنده (gross، کمیسیون، سهم قابل پرداخت) همان‌جا حساب می‌شود؛ ثبت در دفتر فروشنده
 *     مرحله‌ی ۴ است و فقط وقتی «تحویل شد و پول رسید».
 *   - خطا (موجودی، روز تحویل، روش پرداخت…) ← CheckoutException و هیچ ردیفی ساخته نمی‌شود.
 *
 * روش‌های پرداخت این مرحله: `cod` (درِ خانه) و `cash_point` (حضوری). درگاه آنلاین مرحله‌ی ۵ است.
 *
 * هر دو روش تحویل **روز** می‌خواهند: درِ خانه با کرایه‌ی روز و وزن، حضوری بی کرایه (روز فقط برای
 * آماده شدن جنس در نمایندگی). شماره‌ی سفارش فقط عدد است و هر سفارش یک **کد تحویل** پنج‌رقمی دارد.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly PricingService $pricing,
        private readonly ProductCatalog $catalog,
        private readonly DeliveryCalculator $delivery,
        private readonly CommissionService $commission,
        private readonly StockLedger $stock,
        private readonly StoreInfo $store,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @throws CheckoutException
     */
    public function place(Cart $cart, CheckoutData $data): Order
    {
        $this->checkDailyLimit($data);
        $method = $this->paymentMethod($data);
        [$zone, $point] = $this->deliveryTarget($data);

        return DB::transaction(function () use ($cart, $data, $method, $zone, $point): Order {
            $lines = $this->lockedLines($cart);

            // ── تحویل: وزن، روز و کرایه (دوباره در سرور) ──
            $grams = 0;
            $deliveryFee = $this->delivery->pickupFee();
            $deliveryDate = null;
            if ($data->deliveryMethod === DeliveryCalculator::METHOD_PICKUP) {
                // حضوری بی کرایه است، ولی روز می‌خواهد تا جنس در نمایندگی آماده شود (۲ قوس ۱۴۰۵)
                $option = $this->delivery->pickupOption((string) $data->deliveryDate);
                if ($option === null) {
                    throw new CheckoutException('delivery_date_invalid');
                }
                $deliveryDate = $option->dateString();
            }
            if ($data->deliveryMethod === DeliveryCalculator::METHOD_DELIVERY) {
                $grams = $this->delivery->totalWeight($zone, $lines->map(fn (array $l) => ['variant' => $l['variant'], 'qty' => $l['qty']]));
                if ($this->delivery->isTooHeavy($zone, $grams)) {
                    throw new CheckoutException('delivery_too_heavy');
                }
                $option = $this->delivery->option($zone, $grams, (string) $data->deliveryDate);
                if ($option === null) {
                    throw new CheckoutException('delivery_date_invalid');
                }
                $deliveryFee = $option->total;
                $deliveryDate = $option->dateString();
            }

            $subtotal = $lines->reduce(fn (string $c, array $l) => Money::add($c, $l['lineTotal']), '0.00');
            $paymentFee = Money::of($method->fee);
            $total = Money::add(Money::add($subtotal, $deliveryFee), $paymentFee);
            $this->checkAmountLimits($method, $total);

            // ── سفارش والد ──
            $order = new Order([
                'customer_id' => $data->customerId,
                'purchaser_name' => $data->purchaserName,
                'purchaser_phone' => $data->purchaserPhone,
                'purchaser_email' => $data->purchaserEmail,
                'purchaser_country' => $data->purchaserCountry,
                'is_gift' => $data->isGift,
                'recipient_name' => $data->recipientName,
                'recipient_phone' => $data->recipientPhone,
                'province_id' => $data->deliveryMethod === DeliveryCalculator::METHOD_DELIVERY ? $data->provinceId : null,
                'city' => $data->deliveryMethod === DeliveryCalculator::METHOD_DELIVERY ? $data->city : null,
                'district' => $data->deliveryMethod === DeliveryCalculator::METHOD_DELIVERY ? $data->district : null,
                'address_line' => $data->deliveryMethod === DeliveryCalculator::METHOD_DELIVERY ? $data->addressLine : null,
                'delivery_instructions' => $data->deliveryInstructions,
                'payment_method_code' => $method->code,
                'currency_code' => 'AFN',
                'delivery_method' => $data->deliveryMethod,
                'delivery_mode' => 'consolidated',
                'delivery_zone_id' => $zone?->id,
                'delivery_date' => $deliveryDate,
                'delivery_weight_grams' => $grams,
                'collection_point_id' => $point?->id,
                'customer_note' => $data->customerNote,
                'source' => $data->source,
                'needs_stock_check' => $lines->contains(fn (array $l) => $l['backordered']),
                'locale' => $data->locale,
                'ip' => $data->ip,
                'user_agent' => $data->userAgent === null ? null : mb_substr($data->userAgent, 0, 255),
                'placed_at' => now(),
                'subtotal' => $subtotal,
                'discount_total' => '0.00',
                'delivery_fee' => $deliveryFee,
                'payment_fee' => $paymentFee,
                'total' => $total,
            ]);
            $order->forceFill([
                'status' => Order::STATUS_PENDING,
                'payment_status' => 'awaiting_collection',
                'paid_total' => '0.00',
                'refunded_total' => '0.00',
                // کد تحویل: مشتری آن را هنگام گرفتن جنس به پیک یا نمایندگی می‌گوید
                'delivery_code' => Order::generateDeliveryCode(),
            ])->save();
            $order->forceFill(['number' => Order::numberFor($order->id)])->save();

            // ── سفارش هر فروشنده + خط‌ها ──
            $sellerOrders = [];
            $reserve = [];
            $index = 0;
            foreach ($lines->groupBy('sellerKey') as $group) {
                $sellerOrder = $this->createSellerOrder($order, ++$index, $group);
                $sellerOrders[] = $sellerOrder;
                foreach ($group as $line) {
                    $reserve[] = [$line['variant']->id, $line['qty'], $sellerOrder->id];
                }
                $this->history('seller_order', $sellerOrder->id, null, 'pending', null);
            }

            // رزرو موجودی، همیشه به ترتیب نوع (جلوگیری از deadlock)
            usort($reserve, fn (array $a, array $b) => $a[0] <=> $b[0]);
            foreach ($reserve as [$variantId, $qty, $sellerOrderId]) {
                $this->stock->reserve($variantId, $qty, 'seller_order', $sellerOrderId);
            }

            // ── پرداخت (نقد: منتظر گرفتن پول) ──
            $payment = new Payment([
                'order_id' => $order->id,
                'method_code' => $method->code,
                'currency_code' => 'AFN',
                'attempt_no' => 1,
            ]);
            $payment->forceFill([
                'amount' => $total,
                'status' => Payment::AWAITING_COLLECTION,
                'idempotency_key' => 'order:'.$order->id.':attempt:1',
            ])->save();

            $this->history('order', $order->id, null, Order::STATUS_PENDING, null);
            $this->history('payment', $payment->id, null, Payment::AWAITING_COLLECTION, null);

            $this->carts->clear($cart);

            $this->logger->log('order.placed', $order, [
                'number' => $order->number,
                'total' => $total,
                'method' => $method->code,
                'delivery' => $data->deliveryMethod,
                'sellers' => count($sellerOrders),
            ]);

            return $order->load(['sellerOrders.items', 'payments']);
        });
    }

    // ───────────────────────── اعتبارسنجی پیش از transaction ─────────────────────────

    private function paymentMethod(CheckoutData $data): PaymentMethod
    {
        $method = PaymentMethod::query()->active()->where('code', $data->paymentMethodCode)->first();

        // روش نقد همیشه ممکن است؛ درگاه آنلاین فقط وقتی که واقعاً پیکربندی شده باشد
        // (امروز هیچ درگاهی آماده نیست — حساب‌پی مرحله‌ی ۵، بعد از حساب پذیرنده و کلید API).
        if ($method === null || ! app(PaymentGatewayManager::class)->isReady($method)) {
            throw new CheckoutException('payment_unavailable');
        }
        if (! $method->fitsDelivery($data->deliveryMethod)) {
            throw new CheckoutException('payment_not_for_delivery');
        }

        return $method;
    }

    /** @return array{0: DeliveryZone|null, 1: CollectionPoint|null} */
    private function deliveryTarget(CheckoutData $data): array
    {
        if ($data->deliveryMethod === DeliveryCalculator::METHOD_PICKUP) {
            if (! $this->store->pickupEnabled()) {
                throw new CheckoutException('pickup_unavailable');
            }
            $points = CollectionPoint::query()->active();
            $point = $data->collectionPointId === null ? $points->first() : $points->whereKey($data->collectionPointId)->first();
            if ($point === null) {
                throw new CheckoutException($data->collectionPointId === null ? 'pickup_unavailable' : 'collection_point_invalid');
            }

            return [null, $point];
        }

        if ($data->deliveryMethod !== DeliveryCalculator::METHOD_DELIVERY) {
            throw new CheckoutException('delivery_unavailable');
        }
        if ($data->provinceId === null || trim((string) $data->addressLine) === '') {
            throw new CheckoutException('address_required');
        }
        $zone = $this->delivery->zoneFor(Province::query()->find($data->provinceId));
        if ($zone === null) {
            throw new CheckoutException('delivery_unavailable');
        }

        return [$zone, null];
    }

    /**
     * سقف سفارش هر مشتری در ۲۴ ساعت (خواست صاحب پروژه، ۲ قوس ۱۴۰۵).
     *
     * جلوی کسی را می‌گیرد که پشت سر هم سفارش «پرداخت هنگام تحویل» یا «حضوری» ثبت کند و هیچ‌وقت
     * نگیرد. شمارش با **شماره‌ی موبایل خریدار** (و حساب مشتری، اگر وارد شده باشد) است، نه با IP یا
     * کوکی، تا با عوض کردن مرورگر دور نخورد. هر وضعیتی حساب می‌شود (حتی لغو‌شده) تا سقف معنا داشته
     * باشد؛ مدیر در پنل سفارش دستی می‌سازد و این سقف روی آن نیست (`source !== 'web'`).
     * تنظیم: «تنظیمات فروشگاه ← سقف سفارش روزانه»؛ ۰ = بی سقف.
     */
    private function checkDailyLimit(CheckoutData $data): void
    {
        if ($data->source !== 'web') {
            return;
        }
        $max = (int) app(Settings::class)->get('orders.max_per_day');
        if ($max < 1) {
            return;
        }

        $placed = Order::query()
            ->where('placed_at', '>=', now()->subDay())
            ->where(function ($query) use ($data): void {
                $query->where('purchaser_phone', $data->purchaserPhone);
                if ($data->customerId !== null) {
                    $query->orWhere('customer_id', $data->customerId);
                }
            })
            ->count();

        if ($placed >= $max) {
            throw new CheckoutException('too_many_orders');
        }
    }

    private function checkAmountLimits(PaymentMethod $method, string $total): void
    {
        if (($method->min_total !== null && Money::compare($total, $method->min_total) < 0)
            || ($method->max_total !== null && Money::compare($total, $method->max_total) > 0)) {
            throw new CheckoutException('payment_unavailable');
        }
    }

    // ───────────────────────── خط‌های سبد زیر قفل ─────────────────────────

    /**
     * خط‌های سبد با نوع‌های قفل‌شده و قیمت زنده. مشکل (ناموجود، کم‌موجود، فروخته‌نمی‌شود) ← خطا.
     *
     * @return Collection<int, array{variant: ProductVariant, qty: int, unit: string, lineTotal: string, saving: string, backordered: bool, sellerKey: string, seller: Seller|null}>
     */
    private function lockedLines(Cart $cart): Collection
    {
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            throw new CheckoutException('empty_cart');
        }

        // قفل به ترتیب شناسه‌ی نوع
        $variants = ProductVariant::query()
            ->with(['product.category', 'product.seller', 'product.images'])
            ->whereIn('id', $items->pluck('variant_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $visible = $this->catalog->base()
            ->whereIn('products.id', $variants->pluck('product_id')->unique())
            ->pluck('products.id')->flip();

        $lines = collect();
        foreach ($items as $item) {
            $variant = $variants->get($item->variant_id);
            if ($variant === null || ! $variant->is_active || ! $visible->has($variant->product_id)) {
                throw new CheckoutException('cart_problems');
            }
            $qty = (int) $item->qty;
            if ($qty < 1 || $qty > $this->carts->maxQty($variant)) {
                // سقف هر خط و موجودی قابل فروش (زیر قفل)
                throw new CheckoutException($this->carts->maxQty($variant) < 1 ? 'out_of_stock' : 'cart_problems');
            }

            $price = $this->pricing->forVariant($variant);
            $lines->push([
                'variant' => $variant,
                'qty' => $qty,
                'unit' => $price->price,
                'lineTotal' => Money::multiply($price->price, $qty),
                'saving' => $price->hasDiscount() ? Money::multiply($price->saving, $qty) : '0.00',
                'backordered' => $variant->allow_backorder && $qty > (int) floor((float) $variant->availableQty()),
                'sellerKey' => 's'.($variant->product->seller_id ?? 0),
                'seller' => $variant->product->seller,
            ]);
        }

        return $lines;
    }

    // ───────────────────────── سفارش یک فروشنده ─────────────────────────

    /**
     * @param  Collection<int, array<string, mixed>>  $group  خط‌های همین فروشنده
     */
    private function createSellerOrder(Order $order, int $index, Collection $group): SellerOrder
    {
        /** @var Seller|null $seller */
        $seller = $group->first()['seller'];
        $rates = [];
        $gross = '0.00';
        $commissionTotal = '0.00';
        $saving = '0.00';
        $rows = [];

        foreach ($group as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $product = $variant->product;

            // کپی قاعده‌ی کمیسیون همین لحظه (نرخ هر ترکیب فروشنده/دسته یک بار حساب می‌شود)
            $key = $product->category_id;
            $rate = $rates[$key] ??= $this->commission->rateFor($seller, $product->category);
            $commission = $seller === null ? '0.00' : Money::percentOf($line['lineTotal'], $rate->rate);
            $sellerAmount = $seller === null ? '0.00' : Money::sub($line['lineTotal'], $commission);

            $gross = Money::add($gross, $line['lineTotal']);
            $commissionTotal = Money::add($commissionTotal, $commission);
            $saving = Money::add($saving, $line['saving']);
            $rows[] = $this->itemRow($variant, $line, $rate, $commission, $sellerAmount);
        }

        $sellerOrder = new SellerOrder;
        $sellerOrder->forceFill([
            'order_id' => $order->id,
            'number' => $order->number.'-'.$index,
            'seller_id' => $seller?->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'awaiting_collection',
            'gross_subtotal' => $gross,
            'seller_discount_total' => '0.00',
            'platform_discount_total' => '0.00',
            'customer_paid_products' => $gross,
            'commissionable_amount' => $gross,
            'commission_total' => $commissionTotal,
            'delivery_fee' => '0.00',                              // تحویل یکپارچه: کرایه روی سفارش والد
            'delivery_fee_payee' => 'platform',
            'gateway_fee_allocated' => '0.00',
            'gateway_fee_charged_to_seller' => '0.00',
            'refund_adjustments' => '0.00',
            'other_deductions' => '0.00',
            // جنس خود بازارک طلب فروشنده ندارد
            'seller_payable' => $seller === null ? '0.00' : Money::sub($gross, $commissionTotal),
        ])->save();

        foreach ($rows as $row) {
            OrderItem::query()->create($row + ['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id]);
        }

        return $sellerOrder;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function itemRow(ProductVariant $variant, array $line, AppliedRate $rate, string $commission, string $sellerAmount): array
    {
        $product = $variant->product;
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'category_id' => $product->category_id,
            'product_name' => mb_substr($product->tr('name'), 0, 200),
            'variant_label' => $variant->label === null ? null : mb_substr((string) $variant->label, 0, 100),
            'sku' => $variant->sku,
            'image_path' => $image?->pathFor(300),
            'unit_price' => $line['unit'],
            'compare_at_price' => $variant->compare_at_price,
            'discount_amount' => $line['saving'],
            'seller_discount' => '0.00',
            'platform_discount' => '0.00',
            'qty' => $line['qty'],
            'line_total' => $line['lineTotal'],
            'commission_type' => 'percent',
            'commission_rate' => $rate->rate,
            'rate_source' => $rate->source,
            'commission_amount' => $commission,
            'seller_amount' => $sellerAmount,
        ];
    }

    private function history(string $type, int $id, ?string $from, string $to, ?int $userId): void
    {
        OrderStatusHistory::query()->create([
            'subject_type' => $type,
            'subject_id' => $id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId,
            'note' => null,
            'created_at' => now(),
        ]);
    }
}
