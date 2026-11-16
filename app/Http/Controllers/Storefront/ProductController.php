<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SlugRedirect;
use App\Services\Catalog\ProductCatalog;
use App\Services\Catalog\ProductPage;
use App\Services\Catalog\ProductSpecs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * صفحه‌ی یک جنس: گالری، انتخاب نوع (سایز/رنگ)، قیمت، مشخصات، جنس مرتبط.
 */
class ProductController extends Controller
{
    public function show(Request $request, string $slug, ProductCatalog $catalog, ProductPage $page, ProductSpecs $specs): View|RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->first();

        if ($product === null) {
            // آدرس قدیمی: دائمی (۳۰۱) به آدرس تازه
            $targetId = SlugRedirect::targetFor('product', $slug);
            $target = $targetId === null ? null : Product::query()->find($targetId);
            abort_if($target === null, 404);

            return redirect()->route('product.show', ['slug' => $target->slug], 301);
        }

        $product->load(['category', 'brand', 'seller']);

        $visible = $product->category !== null
            && $product->category->isVisible()
            && Product::query()->published()->whereKey($product->id)->exists();
        // مدیر پنل پیش‌نمایش پیش‌نویس و غیرفعال را هم می‌بیند
        $preview = ! $visible && (bool) $request->user('web')?->role->canManageContent();
        abort_unless($visible || $preview, 404);

        if ($visible) {
            $this->countView($request, $product);
        }

        return view('storefront.products.show', [
            'product' => $product,
            'category' => $product->category,
            'ancestors' => $product->category->ancestors(),
            'preview' => $preview,
            'data' => $page->build($product),
            'specs' => $specs->for($product),
            'related' => $visible ? $catalog->related($product) : collect(),
        ]);
    }

    /** هر نشست (مرورگر) یک بار برای هر جنس شمرده می‌شود؛ بی تغییر دادن «آخرین تغییر» و بی ثبت در تاریخچه. */
    private function countView(Request $request, Product $product): void
    {
        $seen = (array) $request->session()->get('viewed_products', []);
        if (in_array($product->id, $seen, true)) {
            return;
        }
        DB::table('products')->where('id', $product->id)->increment('views_count');
        $request->session()->put('viewed_products', array_slice([...$seen, $product->id], -100));
    }
}
