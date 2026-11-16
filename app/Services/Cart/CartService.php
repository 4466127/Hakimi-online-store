<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Services\Catalog\PricingService;
use App\Services\Catalog\ProductCatalog;
use App\Services\Delivery\DeliveryCalculator;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * سبد خرید (مرحله‌ی ۴-۱).
 *
 * - مهمان: سبد در دیتابیس با توکن در کوکی (۳۰ روز). عضو: سبد به مشتری وصل است.
 *   وقتی مهمانی که سبد دارد وارد می‌شود، سبد مهمان خودکار با سبد عضو ادغام می‌شود.
 * - قیمت و موجودی همیشه زنده؛ سبد چیزی از آنها نگه نمی‌دارد. تعداد هیچ‌وقت از موجودی قابل فروش
 *   (و سقف هر خط) بیشتر نمی‌شود، مگر جنس «فروش بی موجودی» داشته باشد.
 * - ثبت سفارش (۴-۳) دوباره همه‌چیز را زیر قفل بررسی می‌کند؛ این‌جا فقط برای نمایش و راحتی است.
 */
class CartService
{
    public const COOKIE = 'cart_token';

    /** شمارنده‌ی سربرگ در همین درخواست (کلید: شناسه‌ی شیء درخواست، تا بین درخواست‌ها نشت نکند). */
    private ?int $countCache = null;

    private ?int $countCacheFor = null;

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly DeliveryCalculator $delivery,
    ) {}

    // ───────────────────────── پیدا کردن سبد ─────────────────────────

    /** سبد این بازدیدکننده، بی ساختن سبد تازه. عضو: سبد مهمان را همین‌جا ادغام می‌کند. */
    public function find(Request $request): ?Cart
    {
        $customer = $request->user('customer');
        $token = $this->token($request);
        $guest = $token === null ? null : Cart::query()->where('token', $token)->whereNull('customer_id')->first();

        if ($customer === null) {
            return $guest;
        }

        $member = Cart::query()->where('customer_id', $customer->id)->first();
        if ($guest !== null) {
            $member ??= $this->createFor($customer->id);
            $this->merge($guest, $member);
            Cookie::queue(Cookie::forget(self::COOKIE));
        }

        return $member;
    }

    /** سبد این بازدیدکننده؛ اگر نداشت می‌سازد (برای «افزودن به سبد»). */
    public function resolve(Request $request): Cart
    {
        $cart = $this->find($request);
        if ($cart !== null) {
            return $cart;
        }

        $customer = $request->user('customer');
        $cart = $this->createFor($customer?->id);
        if ($customer === null) {
            Cookie::queue(self::COOKIE, $cart->token, 60 * 24 * 30);
        }

        return $cart;
    }

    /** تعداد کل اجناس سبد برای شمارنده‌ی سربرگ (یک پرسش، بی ساختن سبد). */
    public function count(Request $request): int
    {
        if ($this->countCache !== null && $this->countCacheFor === spl_object_id($request)) {
            return $this->countCache;
        }
        $cart = $this->find($request);
        $this->countCacheFor = spl_object_id($request);

        return $this->countCache = $cart === null ? 0 : (int) CartItem::query()->where('cart_id', $cart->id)->sum('qty');
    }

    // ───────────────────────── تغییر سبد ─────────────────────────

    /**
     * یک نوع را به سبد اضافه می‌کند (به تعداد قبلی اضافه می‌شود).
     *
     * @return int تعداد نهایی این خط؛ اگر از آنچه خواسته شد کمتر بود، موجودی (یا سقف) کم بود
     *
     * @throws CartException اگر جنس یا نوع فروخته نمی‌شود یا موجودی ندارد
     */
    public function add(Cart $cart, int $variantId, int $qty = 1): int
    {
        $variant = $this->sellableVariant($variantId);
        $max = $this->maxQty($variant);
        if ($max < 1) {
            throw new CartException('out_of_stock');
        }

        return DB::transaction(function () use ($cart, $variant, $qty, $max): int {
            $item = $cart->items()->where('variant_id', $variant->id)->lockForUpdate()->first();
            $final = min(max(1, $qty) + ($item?->qty ?? 0), $max);

            if ($item === null) {
                $cart->items()->create(['variant_id' => $variant->id, 'qty' => $final]);
            } else {
                $item->update(['qty' => $final]);
            }
            $this->touch($cart);

            return $final;
        });
    }

    /**
     * تعداد یک خط را عوض می‌کند. صفر یا کمتر = حذف.
     *
     * @return int تعداد نهایی (۰ اگر حذف شد)
     *
     * @throws CartException اگر خط مال این سبد نیست یا آن جنس دیگر فروخته نمی‌شود
     */
    public function setQty(Cart $cart, int $itemId, int $qty): int
    {
        $item = $cart->items()->whereKey($itemId)->first();
        if ($item === null) {
            throw new CartException('not_found');
        }
        if ($qty <= 0) {
            $item->delete();
            $this->touch($cart);

            return 0;
        }

        $max = $this->maxQty($this->sellableVariant($item->variant_id));
        if ($max < 1) {
            throw new CartException('out_of_stock');
        }
        $item->update(['qty' => min($qty, $max)]);
        $this->touch($cart);

        return $item->qty;
    }

    public function remove(Cart $cart, int $itemId): void
    {
        $cart->items()->whereKey($itemId)->delete();
        $this->touch($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $this->touch($cart);
    }

    /** سبد مهمان را در سبد عضو می‌ریزد (تعدادها جمع، با رعایت موجودی) و سبد مهمان را پاک می‌کند. */
    public function merge(Cart $guest, Cart $member): void
    {
        DB::transaction(function () use ($guest, $member): void {
            foreach ($guest->items()->get() as $line) {
                try {
                    $this->add($member, $line->variant_id, $line->qty);
                } catch (CartException) {
                    // جنسی که دیگر فروخته نمی‌شود به سبد عضو نمی‌رود
                }
            }
            $guest->delete();
        });
    }

    // ───────────────────────── خواندن سبد ─────────────────────────

    /**
     * خط‌های سبد با قیمت و موجودی زنده.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(Cart $cart): Collection
    {
        $items = $cart->items()->with(['variant.product.images'])->get();
        $visible = $items->isEmpty() ? [] : $this->catalog->base()
            ->whereIn('products.id', $items->pluck('variant.product_id')->filter()->unique())
            ->pluck('products.id')->flip()->all();
        $defaultWeight = $this->defaultWeight();

        return $items->map(function (CartItem $item) use ($visible, $defaultWeight): CartLine {
            $variant = $item->variant;
            $product = $variant->product;
            $sellable = $variant->is_active && isset($visible[$product->id]);
            $max = $sellable ? $this->maxQty($variant) : 0;
            $price = $this->pricing->forVariant($variant);
            $qty = $item->qty;

            $problem = match (true) {
                ! $sellable => 'unavailable',
                $max < 1 => 'out_of_stock',
                $qty > $max => 'insufficient',
                default => null,
            };
            // «تعداد بیشتر از موجودی»: تا سقف موجودی حساب می‌شود
            $counted = $problem === 'insufficient' ? $max : ($problem === null ? $qty : 0);

            return new CartLine(
                item: $item,
                variant: $variant,
                product: $product,
                image: $product->images->firstWhere('is_primary', true) ?? $product->images->first(),
                qty: $qty,
                price: $price,
                lineTotal: Money::multiply($price->price, $counted),
                lineSaving: $price->hasDiscount() ? Money::multiply($price->saving, $counted) : '0.00',
                weightGrams: ($variant->weight_grams ?? $defaultWeight) * $counted,
                maxQty: $max,
                problem: $problem,
            );
        })->values();
    }

    /**
     * جمع سبد: مبلغ اجناس، سود تخفیف، تعداد، وزن. فقط خط‌هایی که حساب می‌شوند.
     *
     * @param  Collection<int, CartLine>  $lines
     * @return array{subtotal: string, savings: string, units: int, weight: int, problems: int}
     */
    public function totals(Collection $lines): array
    {
        $counted = $lines->filter(fn (CartLine $l) => $l->counts());

        return [
            'subtotal' => $counted->reduce(fn (string $c, CartLine $l) => Money::add($c, $l->lineTotal), '0.00'),
            'savings' => $counted->reduce(fn (string $c, CartLine $l) => Money::add($c, $l->lineSaving), '0.00'),
            'units' => (int) $counted->sum(fn (CartLine $l) => $l->problem === 'insufficient' ? $l->maxQty : $l->qty),
            'weight' => (int) $counted->sum('weightGrams'),
            'problems' => $lines->filter(fn (CartLine $l) => $l->problem !== null)->count(),
        ];
    }

    /**
     * کرایه‌ی تقریبی برای متن سبد: «از … تا … افغانی» (طبق وزن و روز تحویل) در اولین منطقه‌ی تحویل فعال.
     *
     * @return array{min: string, max: string}|array{tooHeavy: true}|null null اگر منطقه‌ای نیست
     */
    public function deliveryEstimate(int $grams): ?array
    {
        $zone = $this->defaultZone();
        if ($zone === null) {
            return null;
        }
        if ($this->delivery->isTooHeavy($zone, $grams)) {
            return ['tooHeavy' => true];
        }
        $totals = collect($this->delivery->options($zone, $grams))->pluck('total');
        if ($totals->isEmpty()) {
            return null;
        }

        return [
            'min' => $totals->sort(fn ($a, $b) => Money::compare($a, $b))->first(),
            'max' => $totals->sort(fn ($a, $b) => Money::compare($b, $a))->first(),
        ];
    }

    // ───────────────────────── کمکی ─────────────────────────

    /** بیشترین تعداد قابل سفارش این نوع همین حالا (سقف هر خط و موجودی قابل فروش). */
    public function maxQty(ProductVariant $variant): int
    {
        $cap = max(1, (int) config('store.cart.max_qty_per_line', 20));
        if ($variant->allow_backorder) {
            return $cap;
        }

        return max(0, min($cap, (int) floor((float) $variant->availableQty())));
    }

    private function sellableVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);
        if ($variant === null || ! $variant->is_active) {
            throw new CartException('unavailable');
        }
        $visible = $this->catalog->base()->whereKey($variant->product_id)->exists();
        if (! $visible) {
            throw new CartException('unavailable');
        }

        return $variant;
    }

    private function createFor(?int $customerId): Cart
    {
        return Cart::query()->create([
            'token' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function touch(Cart $cart): void
    {
        $cart->forceFill(['expires_at' => now()->addDays(30)])->save();
        $this->countCache = null;
    }

    private function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && Str::isUuid($token) ? $token : null;
    }

    private function defaultZone(): ?DeliveryZone
    {
        $province = Province::query()->deliverable()->first();

        return $this->delivery->zoneFor($province);
    }

    private function defaultWeight(): int
    {
        return (int) ($this->defaultZone()?->default_item_weight_grams ?? 500);
    }
}
