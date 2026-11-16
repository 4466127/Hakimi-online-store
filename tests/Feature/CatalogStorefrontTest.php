<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Services\Catalog\ListingFilters;
use App\Services\Catalog\ProductImageService;
use App\Services\Catalog\ProductService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * مرحله‌ی ۳-۳: صفحه‌ی دسته با اجناس، فیلتر و مرتب‌سازی، جستجو، صفحه‌ی جنس، صفحه‌ی اصلی.
 */
class CatalogStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Category $root;

    private Category $group;

    private Category $formal;

    private Attribute $size;

    private Attribute $color;

    private Attribute $material;

    /** @var array<string, int> */
    private array $opt = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = Category::factory()->create(['slug' => 'shoes-root', 'name' => ['fa' => 'بوت و کفش']]);
        $this->group = Category::factory()->childOf($this->root)->create(['slug' => 'mens', 'name' => ['fa' => 'بوت مردانه']]);
        $this->formal = Category::factory()->childOf($this->group)->create(['slug' => 'formal', 'name' => ['fa' => 'بوت رسمی']]);

        $this->size = Attribute::factory()->create(['code' => 'size', 'name' => ['fa' => 'سایز'], 'is_variant' => true, 'is_filterable' => true, 'position' => 1]);
        $this->color = Attribute::factory()->create(['code' => 'color', 'name' => ['fa' => 'رنگ'], 'is_variant' => true, 'is_filterable' => true, 'position' => 2]);
        $this->material = Attribute::factory()->create(['code' => 'material', 'name' => ['fa' => 'جنس رویه'], 'is_filterable' => true, 'position' => 3]);
        foreach (['41', '42'] as $i => $v) {
            $this->opt['s'.$v] = $this->size->options()->create(['slug' => $v, 'value' => ['fa' => $v], 'position' => $i])->id;
        }
        $this->opt['black'] = $this->color->options()->create(['slug' => 'black', 'value' => ['fa' => 'سیاه'], 'color_hex' => '#111111'])->id;
        $this->opt['brown'] = $this->color->options()->create(['slug' => 'brown', 'value' => ['fa' => 'قهوه‌ای'], 'color_hex' => '#7b4a1e'])->id;
        $this->opt['leather'] = $this->material->options()->create(['slug' => 'leather', 'value' => ['fa' => 'چرم طبیعی']])->id;
        $this->opt['fabric'] = $this->material->options()->create(['slug' => 'fabric', 'value' => ['fa' => 'پارچه']])->id;

        foreach ([$this->size, $this->color, $this->material] as $i => $attribute) {
            $this->root->catalogAttributes()->attach($attribute->id, ['is_required' => false, 'position' => $i]);
        }
    }

    /**
     * جنس را از راه سرویس می‌سازد (مثل پنل).
     *
     * @param  array<string, mixed>  $override
     */
    private function make(string $name, array $override = []): Product
    {
        return app(ProductService::class)->save(null, $override + [
            'category_id' => $this->formal->id,
            'name' => ['fa' => $name],
            'status' => 'active',
            'variants' => [['price' => 1000, 'stock_qty' => 5, 'option_ids' => [$this->opt['s42'], $this->opt['black']]]],
        ]);
    }

    public function test_home_shows_featured_and_latest_but_not_hidden_products(): void
    {
        $this->make('بوت ویژه', ['is_featured' => true]);
        $this->make('بوت معمولی');
        $this->make('بوت پیش‌نویس', ['status' => 'draft']);

        $response = $this->get('/')->assertOk()
            ->assertSee(__('store.home.featured_heading'))
            ->assertSee(__('store.home.latest_heading'))
            ->assertSee('بوت ویژه')
            ->assertSee('بوت معمولی')
            ->assertDontSee('بوت پیش‌نویس');

        // محصول ویژه در «تازه‌ها» تکرار نمی‌شود
        $this->assertSame(1, substr_count($response->getContent(), 'بوت ویژه</h3>'));
    }

    public function test_home_without_products_has_no_product_sections(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee(__('store.home.featured_heading'))
            ->assertDontSee(__('store.home.latest_heading'));
    }

    public function test_category_page_lists_descendants_and_hides_unsellable_products(): void
    {
        $approved = Seller::factory()->status(SellerStatus::Approved)->create();
        $suspended = Seller::factory()->status(SellerStatus::Suspended)->create();

        $this->make('بوت در زیردسته');
        $this->make('بوت در گروه', ['category_id' => $this->group->id]);
        $this->make('بوت فروشنده', ['seller_id' => $approved->id]);
        $this->make('بوت پیش‌نویس', ['status' => 'draft']);
        $this->make('بوت زمان‌دار', ['published_at' => now()->addDay()]);
        $closed = $this->make('بوت فروشنده‌ی بسته');
        $closed->forceFill(['seller_id' => $suspended->id])->save();
        $noPrice = $this->make('بوت بی نوع فعال');
        $noPrice->variants()->update(['is_active' => false]);
        app(ProductService::class)->refresh($noPrice);
        $other = Category::factory()->create(['slug' => 'other-root', 'name' => ['fa' => 'دسته‌ی دیگر']]);
        $this->make('جنس دسته‌ی دیگر', ['category_id' => $other->id, 'variants' => [['price' => 100, 'stock_qty' => 1]]]);

        $this->get('/category/shoes-root')->assertOk()
            ->assertSee('بوت در زیردسته')->assertSee('بوت در گروه')->assertSee('بوت فروشنده</h3>', false)
            ->assertDontSee('بوت پیش‌نویس')->assertDontSee('بوت زمان‌دار')
            ->assertDontSee('بوت فروشنده‌ی بسته')->assertDontSee('بوت بی نوع فعال')
            ->assertDontSee('جنس دسته‌ی دیگر');

        $this->get('/category/formal')->assertOk()->assertSee('بوت در زیردسته')->assertDontSee('بوت در گروه');
    }

    public function test_products_under_an_inactive_subcategory_are_hidden(): void
    {
        $this->make('بوت رسمی');
        $this->make('بوت در گروه', ['category_id' => $this->group->id]);
        $this->formal->update(['is_active' => false]);

        $this->get('/category/shoes-root')->assertOk()->assertSee('بوت در گروه')->assertDontSee('بوت رسمی');
        $this->get('/category/formal')->assertNotFound();
    }

    public function test_empty_category_says_products_will_be_added_soon(): void
    {
        $this->get('/category/formal')->assertOk()->assertSee(__('store.categories.products_empty_heading'));
    }

    public function test_sorting_and_out_of_stock_products_go_last(): void
    {
        $this->make('بوت الف ارزان', ['variants' => [['price' => 100, 'stock_qty' => 3]]]);
        $this->make('بوت ب گران', ['variants' => [['price' => 900, 'stock_qty' => 3]]]);
        $this->make('بوت ج ناموجود', ['variants' => [['price' => 50, 'stock_qty' => 0]]]);
        $popular = $this->make('بوت د پرفروش', ['variants' => [['price' => 500, 'stock_qty' => 3]]]);
        $popular->forceFill(['sold_count' => 40])->saveQuietly();

        $this->get('/category/formal?sort=cheap')->assertSeeInOrder(['بوت الف ارزان', 'بوت د پرفروش', 'بوت ب گران', 'بوت ج ناموجود']);
        $this->get('/category/formal?sort=expensive')->assertSeeInOrder(['بوت ب گران', 'بوت د پرفروش', 'بوت الف ارزان', 'بوت ج ناموجود']);
        $this->get('/category/formal?sort=popular')->assertSeeInOrder(['بوت د پرفروش', 'بوت ج ناموجود']);
        $this->get('/category/formal?sort=nonsense')->assertOk();   // مرتب‌سازی ناشناس بی‌اثر است
        $this->get('/category/formal')->assertSee(__('store.product.out_of_stock'));
    }

    public function test_filters_by_price_brand_stock_and_options(): void
    {
        $nike = Brand::factory()->create(['name' => 'Nike']);
        $puma = Brand::factory()->create(['name' => 'Puma']);
        $this->make('بوت نایک چرمی', ['brand_id' => $nike->id, 'attributes' => [$this->material->id => $this->opt['leather']],
            'variants' => [['price' => 3000, 'stock_qty' => 2, 'option_ids' => [$this->opt['s42'], $this->opt['black']]]]]);
        $this->make('بوت پوما پارچه', ['brand_id' => $puma->id, 'attributes' => [$this->material->id => $this->opt['fabric']],
            'variants' => [['price' => 800, 'stock_qty' => 0, 'option_ids' => [$this->opt['s41'], $this->opt['brown']]]]]);

        // قیمت
        $this->get('/category/formal?min=2000')->assertSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه');
        $this->get('/category/formal?max=1000')->assertSee('بوت پوما پارچه')->assertDontSee('بوت نایک چرمی');
        $this->get('/category/formal?min='.urlencode('۲٬۰۰۰'))->assertSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه');   // ارقام فارسی
        // برند
        $this->get('/category/formal?brand[]='.$puma->id)->assertSee('بوت پوما پارچه')->assertDontSee('بوت نایک چرمی');
        // موجودی
        $this->get('/category/formal?in_stock=1')->assertSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه');
        // نوع‌ساز (سایز، رنگ) و توصیفی (جنس رویه)
        $this->get('/category/formal?f['.$this->size->id.'][]='.$this->opt['s41'])->assertSee('بوت پوما پارچه')->assertDontSee('بوت نایک چرمی');
        $this->get('/category/formal?f['.$this->color->id.'][]='.$this->opt['black'])->assertSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه');
        $this->get('/category/formal?f['.$this->material->id.'][]='.$this->opt['leather'])->assertSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه');
        // بین گزینه‌های یک مشخصه «یا»، بین مشخصه‌ها «و»
        $this->get('/category/formal?f['.$this->size->id.'][]='.$this->opt['s41'].'&f['.$this->size->id.'][]='.$this->opt['s42'])
            ->assertSee('بوت نایک چرمی')->assertSee('بوت پوما پارچه');
        $this->get('/category/formal?f['.$this->size->id.'][]='.$this->opt['s41'].'&f['.$this->color->id.'][]='.$this->opt['black'])
            ->assertDontSee('بوت نایک چرمی')->assertDontSee('بوت پوما پارچه')
            ->assertSee(__('store.listing.empty_heading'));
    }

    public function test_filter_form_offers_only_brands_and_options_that_exist(): void
    {
        $nike = Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'UnusedBrand']);
        $this->make('بوت نایک', ['brand_id' => $nike->id]);

        $this->get('/category/formal')->assertOk()
            ->assertSee('Nike')->assertDontSee('UnusedBrand')
            ->assertSee('سایز')->assertSee('رنگ')
            ->assertSee('سیاه')->assertDontSee('قهوه‌ای')   // رنگی که جنسی ندارد
            ->assertSee('#111111', false);                 // نمونه‌ی رنگ
    }

    public function test_pagination_and_canonical_for_filtered_pages(): void
    {
        Product::factory()->count(25)->active()->withVariant()->create(['category_id' => $this->formal->id]);

        $this->get('/category/formal')->assertOk()->assertSee('rel="next"', false)
            ->assertSee('href="'.url('/category/formal').'"', false);
        $page2 = $this->get('/category/formal?page=2')->assertOk();
        $page2->assertSee('rel="prev"', false)
            ->assertSee('href="'.url('/category/formal?page=2').'"', false);
        $this->assertSame(1, substr_count($page2->getContent(), '</h3>'));

        // صفحه‌ی فیلترشده نباید در گوگل بیاید
        $this->get('/category/formal?in_stock=1')->assertSee('noindex', false);
        $this->get('/category/formal')->assertDontSee('noindex', false);
    }

    public function test_search_finds_by_name_brand_sku_and_category_with_persian_normalization(): void
    {
        $brand = Brand::factory()->create(['name' => 'Samsung']);
        $a = $this->make('کتاب درسی', ['brand_id' => $brand->id]);
        $this->make('بوت چرمی نو');

        $this->get('/search?q='.urlencode('کتاب'))->assertOk()->assertSee('کتاب درسی')->assertDontSee('بوت چرمی نو');
        // «ك» و «ي» عربی با «ک» و «ی» فارسی یکی است
        $this->get('/search?q='.urlencode('كتاب'))->assertSee('کتاب درسی');
        $this->get('/search?q='.urlencode('بوت چرمي'))->assertSee('بوت چرمی نو');
        // برند و SKU
        $this->get('/search?q=samsung')->assertSee('کتاب درسی');
        $this->get('/search?q='.urlencode($a->variants()->value('sku')))->assertSee('کتاب درسی')->assertDontSee('بوت چرمی نو');
        // همه‌ی کلمه‌ها باید بیایند
        $this->get('/search?q='.urlencode('چرمی کتاب'))->assertSee(__('store.listing.empty_heading'));
        // نام دسته
        $this->get('/search?q='.urlencode('بوت رسمی'))->assertSee(__('store.search.in_categories'))->assertSee('/category/formal', false);
        // برای گوگل نیست
        $this->get('/search?q=samsung')->assertSee('noindex', false);
    }

    public function test_empty_and_too_short_search_shows_hint_categories_and_latest(): void
    {
        $this->make('بوت چرمی');

        $this->get('/search')->assertOk()->assertSee(__('store.search.hint'))->assertSee('بوت و کفش')->assertSee('بوت چرمی');
        $this->get('/search?q=ب')->assertOk()->assertSee(__('store.search.too_short'));
        $this->get('/search?q='.str_repeat('x', 500))->assertOk();   // طول بیش از حد بریده می‌شود
    }

    public function test_search_header_box_is_active_now(): void
    {
        $this->get('/')->assertSee('action="'.route('search').'"', false)
            ->assertDontSee('جستجو در بازارک ('.__('store.header.soon').')')
            ->assertDontSee('disabled class="h-11 w-full', false);
    }

    public function test_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/search');
        }
        $this->get('/search')->assertStatus(429);
    }

    public function test_product_page_shows_price_discount_variants_specs_related_and_jsonld(): void
    {
        $nike = Brand::factory()->create(['name' => 'Nike']);
        $product = $this->make('بوت چرمی نایک', [
            'brand_id' => $nike->id,
            'short_description' => ['fa' => 'بوت رسمی برای مجلس'],
            'description' => ['fa' => "خط اول\nخط دوم <b>x</b>"],
            'attributes' => [$this->material->id => $this->opt['leather']],
            'variants' => [
                ['price' => 2500, 'compare_at_price' => 3000, 'stock_qty' => 4, 'cost_price' => 1700, 'option_ids' => [$this->opt['s42'], $this->opt['black']]],
                ['price' => 2700, 'stock_qty' => 0, 'option_ids' => [$this->opt['s41'], $this->opt['black']]],
            ],
        ]);
        $this->make('بوت مشابه');

        $response = $this->get($product->url())->assertOk()
            ->assertSee('بوت چرمی نایک')
            ->assertSee(Money::format('2500'))
            ->assertSee(Money::format('3000'))                       // قیمت قبلی
            ->assertSee(__('store.product.discount_badge', ['percent' => '۱۶']))
            ->assertSee('productPicker(', false)
            ->assertSee('سایز')->assertSee('رنگ')->assertSee('سیاه')
            ->assertSee('چرم طبیعی')->assertSee('جنس رویه')            // مشخصات
            ->assertSee('بوت رسمی برای مجلس')
            ->assertSee('خط اول<br />', false)                         // خط تازه نگه داشته می‌شود
            ->assertDontSee('<b>x</b>', false)                         // HTML توضیح بی‌اثر است
            ->assertSee(__('store.product.related'))->assertSee('بوت مشابه')
            ->assertSee(__('store.product.cod'))
            ->assertDontSee('1700')->assertDontSee('cost_price');       // قیمت خرید هیچ‌وقت در صفحه نیست

        // JSON-LD درست و قابل خواندن
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $response->getContent(), $m);
        $ld = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Product', $ld['@type']);
        $this->assertSame('Nike', $ld['brand']['name']);
        $this->assertSame('AggregateOffer', $ld['offers']['@type']);
        $this->assertSame('2500.00', $ld['offers']['lowPrice']);
        $this->assertSame('2700.00', $ld['offers']['highPrice']);
        $this->assertSame('https://schema.org/InStock', $ld['offers']['availability']);
        $this->assertSame($product->url(), $ld['url']);

        // داده‌ی انتخاب نوع: قیمت فرمت‌شده، موجود بودن، و گزینه‌های هر نوع
        preg_match("#productPicker\\(JSON\\.parse\\('(.+?)'\\)\\)#s", $response->getContent(), $pm);
        $picker = json_decode(json_decode('"'.$pm[1].'"'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(2, $picker['variants']);
        $this->assertTrue($picker['variants'][0]['available']);
        $this->assertFalse($picker['variants'][1]['available']);
        $this->assertSame(Money::format('2500'), $picker['variants'][0]['price']);
        $this->assertSame($this->opt['s42'], $picker['variants'][0]['options'][$this->size->id]);
        $this->assertSame([$this->size->id, $this->color->id], $picker['attrs']);
    }

    public function test_every_page_renders_products_with_images_without_lazy_loading(): void
    {
        // «شدت» مدل‌ها در توسعه بارگذاری تنبل را خطا می‌کند؛ این تست مطمئن می‌کند عکس‌دار بودن هیچ صفحه‌ای را نمی‌شکند
        Storage::fake('public');
        $product = $this->make('بوت عکس‌دار', ['is_featured' => true]);
        $file = UploadedFile::fake()->image('a.jpg', 900, 700);
        app(ProductImageService::class)->add($product, $file->getRealPath());
        $second = UploadedFile::fake()->image('b.jpg', 900, 700);
        app(ProductImageService::class)->add($product, $second->getRealPath(), 'عکس از پهلو');

        $this->get('/')->assertOk()->assertSee('srcset=', false)->assertSee('alt="بوت عکس‌دار"', false);
        $this->get('/category/shoes-root')->assertOk()->assertSee('بوت عکس‌دار');
        $this->get('/search?q='.urlencode('عکس'))->assertOk()->assertSee('بوت عکس‌دار');
        $this->get($product->url())->assertOk()
            ->assertSee('-300.webp', false)
            ->assertSee('alt="عکس از پهلو"', false)
            ->assertSee(__('store.product.show_image', ['n' => '۲']), false);
    }

    public function test_single_variant_product_without_options_has_no_picker_groups(): void
    {
        $product = $this->make('بوت ساده', ['variants' => [['price' => 700, 'stock_qty' => 1]]]);

        $this->get($product->url())->assertOk()->assertSee('بوت ساده')->assertSee(Money::format('700'))->assertDontSee('role="radiogroup"', false);
    }

    public function test_product_page_hides_drafts_but_admin_can_preview_them(): void
    {
        $draft = $this->make('بوت پیش‌نویس', ['status' => 'draft']);

        $this->get($draft->url())->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->get($draft->url())->assertOk()->assertSee('بوت پیش‌نویس')
            ->assertSee(__('store.product.preview', ['status' => __('admin.products.statuses.draft')]))
            ->assertSee('noindex', false);

        // کارمند بدون اجازه‌ی اجناس، پیش‌نمایش نمی‌بیند
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get($draft->url())->assertNotFound();
    }

    public function test_product_in_hidden_category_or_unapproved_seller_or_deleted_is_not_found(): void
    {
        $inHidden = $this->make('بوت دسته‌ی بسته');
        $this->formal->update(['is_active' => false]);
        $this->get($inHidden->url())->assertNotFound();
        $this->formal->update(['is_active' => true]);
        $this->get($inHidden->url())->assertOk();

        $suspended = Seller::factory()->status(SellerStatus::Suspended)->create();
        $inHidden->forceFill(['seller_id' => $suspended->id])->save();
        $this->get($inHidden->url())->assertNotFound();

        $inHidden->forceFill(['seller_id' => null])->save();
        $inHidden->delete();
        $this->get('/p/'.rawurlencode($inHidden->slug))->assertNotFound();
        $this->get('/p/nothing-here')->assertNotFound();
    }

    public function test_changed_slug_redirects_permanently_to_the_new_address(): void
    {
        $product = $this->make('بوت قدیمی');
        $oldSlug = $product->slug;
        $product = app(ProductService::class)->save($product, [
            'category_id' => $this->formal->id, 'name' => ['fa' => 'بوت قدیمی'], 'status' => 'active', 'slug' => 'new-address',
            'variants' => [['id' => $product->variants()->value('id'), 'price' => 1000, 'stock_qty' => 5, 'option_ids' => [$this->opt['s42'], $this->opt['black']]]],
        ]);

        $this->assertNotNull(SlugRedirect::targetFor('product', $oldSlug));
        $this->get('/p/'.rawurlencode($oldSlug))->assertStatus(301)->assertRedirect(route('product.show', ['slug' => 'new-address']));
        $this->get('/p/new-address')->assertOk();
    }

    public function test_views_are_counted_once_per_session_and_not_for_previews(): void
    {
        $product = $this->make('بوت پربازدید');

        $this->get($product->url())->assertOk();
        $this->get($product->url())->assertOk();
        $this->assertSame(1, (int) $product->fresh()->views_count);

        // مرورگر دیگر (نشست تازه)
        $this->flushSession();
        $this->get($product->url())->assertOk();
        $this->assertSame(2, (int) $product->fresh()->views_count);

        $draft = $this->make('پیش‌نویس', ['status' => 'draft']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $this->get($draft->url())->assertOk();
        $this->assertSame(0, (int) $draft->fresh()->views_count);
    }

    public function test_product_page_loads_nothing_from_other_websites_and_has_og_tags(): void
    {
        $product = $this->make('بوت امتحانی');

        $html = $this->get($product->url())->assertOk()
            ->assertSee('og:type" content="product"', false)
            ->assertSee('rel="canonical" href="'.$product->url().'"', false)
            ->getContent();

        preg_match_all('#(?:src|href)="(https?://[^"]+)"#', $html, $m);
        $own = parse_url(config('app.url'), PHP_URL_HOST);
        foreach ($m[1] as $url) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            // لینک واتساپ/تماس و آدرس خود سایت مجاز است
            $this->assertTrue(in_array($host, [$own, 'wa.me', 'localhost', '127.0.0.1'], true), 'فایل یا لینک بیرونی: '.$url);
        }
    }

    public function test_listing_filters_ignore_bad_input(): void
    {
        $request = Request::create('/category/x', 'GET', [
            'q' => '  بوت  چرمی ',
            'sort' => 'hack',
            'min' => '5000', 'max' => '1000',
            'brand' => ['3', 'x', '-1', '3'],
            'f' => ['5' => ['7', 'abc'], 'bad' => ['1'], '0' => ['2']],
            'in_stock' => 'yes',
        ]);
        $filters = ListingFilters::fromRequest($request);

        $this->assertSame('new', $filters->sort);
        $this->assertSame(['بوت', 'چرمی'], $filters->tokens);
        $this->assertSame('1000', $filters->minPrice);   // کمینه و بیشینه جابه‌جا شد
        $this->assertSame('5000', $filters->maxPrice);
        $this->assertSame([3], $filters->brandIds);
        $this->assertSame([5 => [7]], $filters->options);
        $this->assertTrue($filters->inStock);
        $this->assertSame(4, $filters->activeCount());

        $garbage = ListingFilters::fromRequest(Request::create('/x', 'GET', ['q' => ['x'], 'min' => 'abc', 'max' => ['x'], 'brand' => 'z', 'f' => 'nope', 'sort' => ['a']]));
        $this->assertSame('', $garbage->query);
        $this->assertNull($garbage->minPrice);
        $this->assertNull($garbage->maxPrice);
        $this->assertSame([], $garbage->brandIds);
        $this->assertSame([], $garbage->options);
        $this->assertSame(0, $garbage->activeCount());
    }
}
