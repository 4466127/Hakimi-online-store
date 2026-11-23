<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog\ProductService;

/**
 * تنها جایی که موجودی آنلاین (stock_qty و reserved_qty) به‌خاطر سفارش تغییر می‌کند.
 * قابل فروش = stock_qty − reserved_qty.
 *
 * - reserve: هنگام ثبت سفارش، رزرو می‌شود (دو نفر همزمان آخرین قلم را نمی‌خرند).
 * - release: سفارش لغو شد، رزرو آزاد می‌شود.
 * - sell:    تحویل شد، موجودی کم می‌شود و رزرو آزاد.
 *
 * همه‌ی متدها باید داخل یک transaction صدا زده شوند؛ ردیف نوع با `lockForUpdate` گرفته می‌شود.
 * هر تغییر یک ردیف در دفتر `stock_movements` می‌نویسد، و cache جنس (total_stock) تازه می‌شود.
 */
class StockLedger
{
    public function __construct(private readonly ProductService $products) {}

    public function reserve(int $variantId, int $qty, string $refType, int $refId, ?User $by = null, ?string $note = null): void
    {
        $variant = $this->lock($variantId);
        $variant->forceFill(['reserved_qty' => bcadd((string) $variant->reserved_qty, (string) $qty, 3)])->save();
        $this->record($variant, 'reserve', (string) $qty, $refType, $refId, $by, $note);
    }

    public function release(int $variantId, int $qty, string $refType, int $refId, ?User $by = null, ?string $note = null): void
    {
        $variant = $this->lock($variantId);
        $new = $this->nonNegative(bcsub((string) $variant->reserved_qty, (string) $qty, 3));
        $actual = bcsub((string) $variant->reserved_qty, $new, 3);
        $variant->forceFill(['reserved_qty' => $new])->save();
        $this->record($variant, 'release', bcmul($actual, '-1', 3), $refType, $refId, $by, $note);
    }

    public function sell(int $variantId, int $qty, string $refType, int $refId, ?User $by = null, ?string $note = null): void
    {
        $variant = $this->lock($variantId);
        $newStock = $this->nonNegative(bcsub((string) $variant->stock_qty, (string) $qty, 3));
        $stockDelta = bcsub((string) $variant->stock_qty, $newStock, 3);          // کمی که واقعاً کم شد («فروش بی موجودی» می‌تواند کمتر از qty باشد)
        $variant->forceFill([
            'stock_qty' => $newStock,
            'reserved_qty' => $this->nonNegative(bcsub((string) $variant->reserved_qty, (string) $qty, 3)),
        ])->save();
        $this->record($variant, 'sale', bcmul($stockDelta, '-1', 3), $refType, $refId, $by, $note);
    }

    private function lock(int $variantId): ProductVariant
    {
        return ProductVariant::query()->with('product')->whereKey($variantId)->lockForUpdate()->firstOrFail();
    }

    private function nonNegative(string $value): string
    {
        return bccomp($value, '0', 3) < 0 ? '0.000' : $value;
    }

    private function record(ProductVariant $variant, string $type, string $delta, string $refType, int $refId, ?User $by, ?string $note): void
    {
        StockMovement::query()->create([
            'variant_id' => $variant->id,
            'type' => $type,
            'qty_delta' => $delta,
            'stock_after' => (string) $variant->stock_qty,
            'reserved_after' => (string) $variant->reserved_qty,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'user_id' => $by?->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        $product = $variant->product;
        if ($product !== null) {
            $this->products->refresh($product);
        }
    }
}
