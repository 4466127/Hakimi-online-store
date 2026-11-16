<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\SlugRedirect;
use App\Services\Catalog\PricingService;
use App\Services\Catalog\ProductImageService;
use App\Services\Catalog\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * مرحله‌ی ۳-۲: ساختن جنس با نوع‌ها، مشخصات، آدرس و ریدایرکت، cache، قیمت و عکس‌ها.
 */
class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $shoes;

    private Attribute $size;

    private Attribute $color;

    /** @var array<string, AttributeOption> */
    private array $opt = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->shoes = Category::factory()->create(['slug' => 'shoes-test', 'name' => ['fa' => 'بوت مردانه']]);
        $this->size = Attribute::factory()->create(['code' => 'size', 'name' => ['fa' => 'سایز'], 'is_variant' => true, 'position' => 1]);
        $this->color = Attribute::factory()->create(['code' => 'color', 'name' => ['fa' => 'رنگ'], 'is_variant' => true, 'position' => 2]);
        foreach (['40', '41', '42'] as $i => $v) {
            $this->opt['s'.$v] = $this->size->options()->create(['slug' => $v, 'value' => ['fa' => $v], 'position' => $i]);
        }
        foreach (['black' => 'سیاه', 'brown' => 'قهوه‌ای'] as $slug => $fa) {
            $this->opt[$slug] = $this->color->options()->create(['slug' => $slug, 'value' => ['fa' => $fa], 'color_hex' => '#000000']);
        }
        $this->shoes->catalogAttributes()->attach($this->size->id, ['is_required' => true, 'position' => 1]);
        $this->shoes->catalogAttributes()->attach($this->color->id, ['is_required' => false, 'position' => 2]);
    }

    /** @var list<UploadedFile> فایل آزمایشی تا پایان تست نگه داشته می‌شود (وگرنه فایل موقت پاک می‌شود). */
    private array $fakes = [];

    private function fakeImage(string $name, int $w, int $h): string
    {
        return ($this->fakes[] = UploadedFile::fake()->image($name, $w, $h))->getRealPath();
    }

    private function service(): ProductService
    {
        return app(ProductService::class);
    }

    /** @param  array<string, mixed>  $override */
    private function data(array $override = []): array
    {
        return $override + [
            'category_id' => $this->shoes->id,
            'name' => ['fa' => 'بوت چرمی مردانه'],
            'status' => 'active',
            'variants' => [
                ['price' => '2500', 'stock_qty' => '4', 'option_ids' => [$this->opt['s42']->id, $this->opt['black']->id]],
            ],
        ];
    }

    private function assertInvalid(string $field, array $data, ?Product $product = null): void
    {
        try {
            $this->service()->save($product, $data);
            $this->fail('انتظار خطا برای '.$field.' بود.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_creates_product_with_variant_slug_cache_and_search_text(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike']);
        $product = $this->service()->save(null, $this->data(['brand_id' => $brand->id]));

        $this->assertSame('بوت-چرمی-مردانه', $product->slug);
        $this->assertNotNull($product->published_at);
        $this->assertTrue($product->isOwnStore());

        $variant = $product->variants()->first();
        $this->assertSame(sprintf('BZ-%06d-1', $product->id), $variant->sku);
        $this->assertSame('42 / سیاه', $variant->label);
        $this->assertTrue($variant->is_default);

        $product->refresh();
        $this->assertSame('2500.00', $product->min_price);
        $this->assertSame('2500.00', $product->max_price);
        $this->assertSame('4.000', $product->total_stock);
        $this->assertTrue($product->has_variants);
        $this->assertStringContainsString('nike', $product->search_text);
        $this->assertStringContainsString('بوت چرمی مردانه', $product->search_text);
        $this->assertStringContainsString('42 سیاه', $product->search_text);
    }

    public function test_variant_matrix_prices_discount_and_cache(): void
    {
        $product = $this->service()->save(null, $this->data(['variants' => [
            ['price' => '2500', 'compare_at_price' => '3000', 'stock_qty' => 2, 'option_ids' => [$this->opt['s40']->id, $this->opt['black']->id]],
            ['price' => '2700', 'stock_qty' => 3, 'option_ids' => [$this->opt['s41']->id, $this->opt['black']->id]],
            ['price' => '2900', 'stock_qty' => 0, 'is_active' => false, 'option_ids' => [$this->opt['s42']->id, $this->opt['brown']->id]],
        ]]))->refresh();

        $this->assertSame(3, $product->variants()->count());
        $this->assertSame('2500.00', $product->min_price);
        $this->assertSame('2700.00', $product->max_price);   // نوع غیرفعال حساب نمی‌شود
        $this->assertSame('3000.00', $product->min_compare_price);
        $this->assertSame('5.000', $product->total_stock);

        $price = app(PricingService::class)->forProduct($product);
        $this->assertTrue($price->isRange);
        $this->assertSame(16, $price->discountPercent);       // ۵۰۰ از ۳۰۰۰
        $this->assertSame('500.00', $price->saving);
        $this->assertTrue($price->hasDiscount());

        $single = app(PricingService::class)->forVariant($product->variants()->where('price', 2700)->first());
        $this->assertFalse($single->hasDiscount());
        $this->assertSame('2700.00', $single->price);
    }

    public function test_bad_variant_data_is_rejected_with_a_message(): void
    {
        $s42 = $this->opt['s42']->id;
        $black = $this->opt['black']->id;
        $other = Attribute::factory()->create(['is_variant' => true])->options()->create(['slug' => 'x', 'value' => ['fa' => 'ایکس']]);

        $this->assertInvalid('variants', $this->data(['variants' => []]));
        $this->assertInvalid('variants.0.price', $this->data(['variants' => [['price' => 'abc', 'option_ids' => [$s42]]]]));
        $this->assertInvalid('variants.0.compare_at_price', $this->data(['variants' => [['price' => '100', 'compare_at_price' => '100', 'option_ids' => [$s42]]]]));
        $this->assertInvalid('variants.0.stock_qty', $this->data(['variants' => [['price' => '100', 'stock_qty' => '-1', 'option_ids' => [$s42]]]]));
        $this->assertInvalid('variants.0.option_ids', $this->data(['variants' => [['price' => '100', 'option_ids' => [$other->id]]]]));
        $this->assertInvalid('variants.0.option_ids', $this->data(['variants' => [['price' => '100', 'option_ids' => [$s42, $this->opt['s41']->id]]]]));
        $this->assertInvalid('variants.1.option_ids', $this->data(['variants' => [
            ['price' => '100', 'option_ids' => [$s42, $black]],
            ['price' => '200', 'option_ids' => [$black, $s42]],
        ]]));
        // سایز برای دسته لازم است و جنس فعال است
        $this->assertInvalid('variants.0.option_ids', $this->data(['variants' => [['price' => '100', 'option_ids' => [$black]]]]));

        $this->assertSame(0, Product::query()->count());
    }

    public function test_required_options_are_only_enforced_for_active_products(): void
    {
        $draft = $this->service()->save(null, $this->data(['status' => 'draft', 'variants' => [['price' => '100', 'option_ids' => []]]]));

        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->published_at);

        $this->assertInvalid('variants.0.option_ids', $this->data(['status' => 'active', 'variants' => [['price' => '100', 'option_ids' => []]]]), $draft);
    }

    public function test_seller_products_need_an_approved_seller(): void
    {
        $pending = Seller::factory()->create();
        $approved = Seller::factory()->status(SellerStatus::Approved)->create();

        $this->assertInvalid('seller_id', $this->data(['seller_id' => $pending->id]));

        $product = $this->service()->save(null, $this->data(['seller_id' => $approved->id]));
        $this->assertFalse($product->isOwnStore());
        $this->assertSame($approved->id, $product->seller_id);
    }

    public function test_slug_is_unique_explicit_duplicates_fail_and_old_slug_redirects(): void
    {
        $a = $this->service()->save(null, $this->data());
        $b = $this->service()->save(null, $this->data());
        $this->assertSame('بوت-چرمی-مردانه', $a->slug);
        $this->assertSame('بوت-چرمی-مردانه-2', $b->slug);

        $this->assertInvalid('slug', $this->data(['slug' => 'بوت چرمی مردانه']));

        // نام عوض شد، آدرس نه (لینک نمی‌شکند)
        $a = $this->service()->save($a, $this->data(['name' => ['fa' => 'نام تازه']]));
        $this->assertSame('بوت-چرمی-مردانه', $a->slug);
        $this->assertSame(0, SlugRedirect::query()->count());

        // آدرس عوض شد ← قدیمی ریدایرکت می‌شود
        $a = $this->service()->save($a, $this->data(['slug' => 'boot-new']));
        $this->assertSame('boot-new', $a->slug);
        $this->assertSame($a->id, SlugRedirect::targetFor('product', 'بوت-چرمی-مردانه'));

        // برگشتن به آدرس قدیمی ریدایرکت را برمی‌دارد (آدرس زنده مهم‌تر است)
        $a = $this->service()->save($a, $this->data(['slug' => 'بوت-چرمی-مردانه']));
        $this->assertNull(SlugRedirect::targetFor('product', 'بوت-چرمی-مردانه'));
        $this->assertSame($a->id, SlugRedirect::targetFor('product', 'boot-new'));
    }

    public function test_update_keeps_sku_removes_dropped_variants_and_keeps_one_default(): void
    {
        $product = $this->service()->save(null, $this->data(['variants' => [
            ['price' => '100', 'option_ids' => [$this->opt['s40']->id]],
            ['price' => '200', 'option_ids' => [$this->opt['s41']->id], 'is_default' => true],
            ['price' => '300', 'option_ids' => [$this->opt['s42']->id]],
        ]]));
        $variants = $product->variants()->get();
        $this->assertSame([false, true, false], $variants->pluck('is_default')->all());

        $keep = $variants[1];
        $product = $this->service()->save($product, $this->data(['variants' => [
            ['id' => $keep->id, 'price' => '250', 'stock_qty' => 9, 'is_default' => true, 'option_ids' => [$this->opt['s41']->id]],
        ]]));

        $this->assertSame(1, $product->variants()->count());
        $left = $product->variants()->first();
        $this->assertSame($keep->id, $left->id);
        $this->assertSame($keep->sku, $left->sku);
        $this->assertSame('250.00', $left->price);
        $this->assertSame('9.000', $product->fresh()->total_stock);
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
    }

    public function test_another_products_variant_id_is_never_hijacked(): void
    {
        $a = $this->service()->save(null, $this->data());
        $b = $this->service()->save(null, $this->data());
        $aVariant = $a->variants()->first();

        $b = $this->service()->save($b, $this->data(['variants' => [['id' => $aVariant->id, 'price' => '111', 'option_ids' => [$this->opt['s42']->id]]]]));

        $this->assertSame('2500.00', $aVariant->fresh()->price);
        $this->assertSame($aVariant->product_id, $a->id);
        $this->assertSame('111.00', $b->variants()->first()->price);
    }

    public function test_descriptive_attributes_are_saved_and_required_only_when_active(): void
    {
        $warranty = Attribute::factory()->type('select')->create(['name' => ['fa' => 'ضمانت'], 'is_variant' => false]);
        $yes = $warranty->options()->create(['slug' => '1y', 'value' => ['fa' => 'یک سال ضمانت']]);
        $material = Attribute::factory()->type('text')->create(['name' => ['fa' => 'جنس رویه']]);
        $weight = Attribute::factory()->type('number')->create(['name' => ['fa' => 'وزن']]);
        $this->shoes->catalogAttributes()->attach($warranty->id, ['is_required' => true, 'position' => 3]);
        $this->shoes->catalogAttributes()->attach($material->id, ['is_required' => false, 'position' => 4]);
        $this->shoes->catalogAttributes()->attach($weight->id, ['is_required' => false, 'position' => 5]);

        try {
            $this->service()->save(null, $this->data());
            $this->fail('ضمانت لازم است');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ضمانت', $e->errors()['attributes'][0]);
        }

        $product = $this->service()->save(null, $this->data(['attributes' => [
            $warranty->id => $yes->id,
            $material->id => 'چرم طبیعی',
            $weight->id => '۱٫۵',
        ]]));

        $rows = $product->attributeValues()->get()->keyBy('attribute_id');
        $this->assertSame($yes->id, $rows[$warranty->id]->attribute_option_id);
        $this->assertSame('چرم طبیعی', $rows[$material->id]->value_text);
        $this->assertSame('1.5000', $rows[$weight->id]->value_number);
        $this->assertStringContainsString('چرم طبیعی', $product->fresh()->search_text);
        $this->assertStringContainsString('یک سال ضمانت', $product->fresh()->search_text);

        // ضمانت از گزینه‌ی مشخصه‌ی دیگر نمی‌آید
        $this->assertInvalid('attributes', $this->data(['attributes' => [$warranty->id => $this->opt['black']->id]]));

        // پیش‌نویس بی ضمانت می‌شود
        $draft = $this->service()->save(null, $this->data(['status' => 'draft']));
        $this->assertSame(0, $draft->attributeValues()->count());
    }

    public function test_related_products_exclude_itself_and_unknown_ids(): void
    {
        $a = $this->service()->save(null, $this->data());
        $b = $this->service()->save(null, $this->data());
        $c = $this->service()->save(null, $this->data());

        $a = $this->service()->save($a, $this->data(['related' => [$a->id, $c->id, $b->id, 99999, $c->id]]));
        $this->assertSame([$c->id, $b->id], $a->relatedProducts()->pluck('products.id')->all());

        // کلید related نیامد ← تغییر نمی‌کند
        $a = $this->service()->save($a, $this->data());
        $this->assertSame(2, $a->relatedProducts()->count());
    }

    public function test_published_scope_hides_drafts_future_and_unapproved_sellers(): void
    {
        $approved = Seller::factory()->status(SellerStatus::Approved)->create();
        $suspended = Seller::factory()->status(SellerStatus::Suspended)->create();

        $own = Product::factory()->active()->create();
        $sellerOk = Product::factory()->active()->ownedBy($approved->id)->create();
        Product::factory()->create();                                                        // پیش‌نویس
        Product::factory()->create(['status' => 'active', 'published_at' => now()->addDay()]);  // برای آینده
        Product::factory()->create(['status' => 'inactive', 'published_at' => now()->subDay()]);
        Product::factory()->active()->ownedBy($suspended->id)->create();                      // فروشنده‌ی بسته‌شده

        $this->assertEqualsCanonicalizing([$own->id, $sellerOk->id], Product::query()->published()->pluck('id')->all());
    }

    public function test_cost_price_is_never_in_json(): void
    {
        $product = $this->service()->save(null, $this->data(['variants' => [['price' => '100', 'cost_price' => '70', 'option_ids' => [$this->opt['s42']->id]]]]));

        $variant = $product->variants()->first();
        $this->assertSame('70.00', $variant->cost_price);
        $this->assertArrayNotHasKey('cost_price', $variant->toArray());
    }

    public function test_images_are_stored_as_three_webp_sizes_first_is_primary_and_removing_cleans_files(): void
    {
        Storage::fake('public');
        $product = $this->service()->save(null, $this->data());
        $images = app(ProductImageService::class);

        $first = $images->add($product, $this->fakeImage('a.jpg', 1400, 900));
        $second = $images->add($product, $this->fakeImage('b.png', 500, 500), 'عکس دوم');

        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->is_primary);
        $this->assertSame([300, 600, 1200], $first->widths());
        $this->assertSame([300, 500], $second->widths());      // عکس کوچک بزرگ نمی‌شود
        foreach ($first->files() as $file) {
            // WebP همیشه؛ AVIF هم اگر سرور بتواند
            $this->assertTrue(str_ends_with($file, '.webp') || str_ends_with($file, '.avif'), $file);
            Storage::disk('public')->assertExists($file);
        }
        foreach ($first->sizes as $file) {
            $this->assertStringEndsWith('.webp', $file);
        }
        $this->assertStringContainsString('300w', $first->srcset());
        $this->assertSame('عکس دوم', $second->altText('نام جنس'));
        $this->assertSame('نام جنس', $first->altText('نام جنس'));
        $this->assertSame($first->id, $product->fresh()->primaryImage()->id);

        $images->remove($first);
        foreach ($first->files() as $file) {
            Storage::disk('public')->assertMissing($file);
        }
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_image_sync_reorders_deletes_and_rejects_bad_files(): void
    {
        Storage::fake('public');
        $product = $this->service()->save(null, $this->data());
        $images = app(ProductImageService::class);
        $a = $images->add($product, $this->fakeImage('a.jpg', 700, 700));
        $b = $images->add($product, $this->fakeImage('b.jpg', 700, 700));
        $c = $images->add($product, $this->fakeImage('c.jpg', 700, 700));

        $images->sync($product, [['id' => $c->id, 'alt' => 'سوم'], ['id' => $a->id]], [$this->fakeImage('d.jpg', 700, 700)]);

        $ordered = $product->images()->get();
        $this->assertCount(3, $ordered);
        $this->assertSame($c->id, $ordered[0]->id);
        $this->assertTrue($ordered[0]->is_primary);
        $this->assertSame($a->id, $ordered[1]->id);
        $this->assertFalse($ordered[2]->is_primary);
        $this->assertNull(ProductImage::query()->find($b->id));
        Storage::disk('public')->assertMissing($b->path);

        $text = $this->fakes[] = UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;');
        try {
            $images->add($product, $text->getRealPath());
            $this->fail('فایل غیرعکس باید رد شود');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('images', $e->errors());
        }
        $this->assertSame(3, $product->images()->count());
    }

    public function test_image_limit_is_enforced(): void
    {
        Storage::fake('public');
        $product = $this->service()->save(null, $this->data());
        $file = $this->fakeImage('a.jpg', 400, 400);

        $this->expectException(ValidationException::class);
        $images = app(ProductImageService::class);
        $images->sync($product, [], array_fill(0, ProductImageService::MAX_IMAGES + 1, $file));
    }
}
