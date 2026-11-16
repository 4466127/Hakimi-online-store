<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\ActivityLog;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * مرحله‌ی ۳-۲: صفحه‌های پنل برای اجناس (لیست، ساختن، ویرایش، ساختن ترکیب‌ها، عمل گروهی).
 */
class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private Category $shoes;

    private Attribute $size;

    private Attribute $color;

    /** @var array<string, int> */
    private array $opt = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->shoes = Category::factory()->create(['slug' => 'shoes-test', 'name' => ['fa' => 'بوت مردانه']]);
        $this->size = Attribute::factory()->create(['code' => 'size', 'name' => ['fa' => 'سایز'], 'is_variant' => true, 'position' => 1]);
        $this->color = Attribute::factory()->create(['code' => 'color', 'name' => ['fa' => 'رنگ'], 'is_variant' => true, 'position' => 2]);
        foreach (['40', '41', '42'] as $i => $v) {
            $this->opt['s'.$v] = $this->size->options()->create(['slug' => $v, 'value' => ['fa' => $v], 'position' => $i])->id;
        }
        foreach (['black' => 'سیاه', 'brown' => 'قهوه‌ای'] as $slug => $fa) {
            $this->opt[$slug] = $this->color->options()->create(['slug' => $slug, 'value' => ['fa' => $fa]])->id;
        }
        $this->shoes->catalogAttributes()->attach($this->size->id, ['is_required' => true, 'position' => 1]);
        $this->shoes->catalogAttributes()->attach($this->color->id, ['is_required' => false, 'position' => 2]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    public function test_list_page_shows_products_with_price_stock_and_search(): void
    {
        $a = app(ProductService::class)->save(null, [
            'category_id' => $this->shoes->id, 'name' => ['fa' => 'بوت چرمی'], 'status' => 'active',
            'variants' => [
                ['price' => 2500, 'stock_qty' => 3, 'option_ids' => [$this->opt['s40']]],
                ['price' => 2900, 'stock_qty' => 0, 'option_ids' => [$this->opt['s41']]],
            ],
        ]);
        $b = app(ProductService::class)->save(null, [
            'category_id' => $this->shoes->id, 'name' => ['fa' => 'چپلی ساده'], 'status' => 'draft',
            'variants' => [['price' => 500, 'stock_qty' => 0, 'option_ids' => [$this->opt['s42']]]],
        ]);

        $this->get('/admin/products')->assertOk()->assertSee('بوت چرمی')->assertSee('چپلی ساده');

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->searchTable('چرمی')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b])
            ->searchTable('')
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a]);
    }

    public function test_bulk_activate_skips_products_without_an_active_variant(): void
    {
        $ok = app(ProductService::class)->save(null, [
            'category_id' => $this->shoes->id, 'name' => ['fa' => 'یک'], 'status' => 'draft',
            'variants' => [['price' => 100, 'option_ids' => [$this->opt['s40']]]],
        ]);
        $none = app(ProductService::class)->save(null, [
            'category_id' => $this->shoes->id, 'name' => ['fa' => 'دو'], 'status' => 'draft',
            'variants' => [['price' => 100, 'is_active' => false, 'option_ids' => [$this->opt['s40']]]],
        ]);

        Livewire::test(ListProducts::class)
            ->selectTableRecords([$ok->id, $none->id])
            ->callAction(TestAction::make('activate')->table()->bulk())
            ->assertNotified();

        $this->assertSame('active', $ok->fresh()->status);
        $this->assertNotNull($ok->fresh()->published_at);
        $this->assertSame('draft', $none->fresh()->status);

        Livewire::test(ListProducts::class)
            ->selectTableRecords([$ok->id])
            ->callAction(TestAction::make('deactivate')->table()->bulk());
        $this->assertSame('inactive', $ok->fresh()->status);
    }

    public function test_create_form_saves_product_variants_specs_and_images(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $warranty = Attribute::factory()->type('text')->create(['name' => ['fa' => 'گارانتی']]);
        $this->shoes->catalogAttributes()->attach($warranty->id, ['is_required' => false, 'position' => 3]);

        $upload = UploadedFile::fake()->image('boot.jpg', 900, 700);
        Storage::disk('local')->put('tmp/product-uploads/boot.jpg', (string) file_get_contents($upload->getRealPath()));

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'بوت چرمی مردانه'],
                'category_id' => $this->shoes->id,
                'status' => 'active',
                'variants' => [
                    ['price' => '2500', 'compare_at_price' => '3000', 'stock_qty' => '4', 'is_active' => true, 'option_ids' => [$this->opt['s42'], $this->opt['black']]],
                    ['price' => '2600', 'stock_qty' => '2', 'is_active' => true, 'option_ids' => [$this->opt['s41'], $this->opt['black']]],
                ],
                'specs' => [$warranty->id => 'یک سال'],
                'new_images' => ['tmp/product-uploads/boot.jpg'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();
        $this->assertSame('بوت-چرمی-مردانه', $product->slug);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame('2500.00', $product->min_price);
        $this->assertSame('6.000', $product->total_stock);
        $this->assertSame('یک سال', $product->attributeValues()->first()->value_text);
        $this->assertSame(1, $product->images()->count());
        $this->assertTrue($product->images()->first()->is_primary);
        $this->assertSame($this->shoes->id, $product->category_id);
        $this->assertNotNull($product->created_by);
        // فایل موقت پاک شد
        Storage::disk('local')->assertMissing('tmp/product-uploads/boot.jpg');
        // در تاریخچه ثبت شد، ولی cache و ستون‌های داخلی نه
        $log = ActivityLog::query()->where('action', 'product.created')->first();
        $this->assertNotNull($log);
    }

    public function test_create_form_shows_service_errors_on_the_right_row(): void
    {
        $component = Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'بوت'],
                'category_id' => $this->shoes->id,
                'status' => 'active',
                'variants' => [
                    ['price' => '100', 'stock_qty' => '1', 'is_active' => true, 'option_ids' => [$this->opt['s42']]],
                    ['price' => '200', 'stock_qty' => '1', 'is_active' => true, 'option_ids' => [$this->opt['s42']]],
                ],
            ])
            ->call('create');

        // خطای «ترکیب تکراری» روی همان ردیف دوم فورم نشان داده می‌شود
        $keys = array_keys($component->errors()->toArray());
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression('/^data\.variants\..+\.option_ids$/', $keys[0]);
        $this->assertSame(0, Product::query()->count());
    }

    public function test_edit_form_is_filled_from_variants_and_specs_and_saves_changes(): void
    {
        $material = Attribute::factory()->type('text')->create(['name' => ['fa' => 'جنس رویه']]);
        $this->shoes->catalogAttributes()->attach($material->id, ['is_required' => false, 'position' => 3]);

        $product = app(ProductService::class)->save(null, [
            'category_id' => $this->shoes->id, 'name' => ['fa' => 'بوت چرمی'], 'status' => 'active',
            'attributes' => [$material->id => 'چرم'],
            'variants' => [
                ['price' => '2500', 'compare_at_price' => '3000', 'stock_qty' => 3, 'option_ids' => [$this->opt['s40']]],
                ['price' => '2600', 'stock_qty' => 2, 'option_ids' => [$this->opt['s41']]],
            ],
        ]);

        $component = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(function (array $state) use ($material): void {
                $this->assertCount(2, $state['variants']);
                $this->assertSame('چرم', $state['specs'][$material->id]);
                $this->assertSame('بوت چرمی', $state['name']['fa']);
                $first = array_values($state['variants'])[0];
                $this->assertSame('2500', $first['price']);       // بی صفرهای اضافه
                $this->assertEquals([$this->opt['s40']], $first['option_ids']);   // Select گزینه‌ها را رشته می‌دهد
            });

        $variants = $component->get('data.variants');
        $firstKey = array_key_first($variants);
        $variants[$firstKey]['price'] = '2400';

        $component->fillForm(['name' => ['fa' => 'بوت چرمی نو'], 'variants' => $variants])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('بوت چرمی نو', $product->tr('name'));
        $this->assertSame('بوت-چرمی', $product->slug);   // نام عوض شد، آدرس نه
        $this->assertSame('2400.00', $product->min_price);
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_generate_variants_action_builds_the_matrix_without_duplicates(): void
    {
        $component = Livewire::test(CreateProduct::class)
            ->fillForm(['category_id' => $this->shoes->id, 'variants' => [
                ['price' => '10', 'option_ids' => [$this->opt['s40'], $this->opt['black']]],
            ]]);

        $component->callAction(
            TestAction::make('generateVariants')->schemaComponent('generate_actions'),
            data: [
                'opt_'.$this->size->id => [$this->opt['s40'], $this->opt['s41']],
                'opt_'.$this->color->id => [$this->opt['black'], $this->opt['brown']],
                'price' => '1500',
                'stock_qty' => '5',
            ],
        );

        $variants = array_values($component->get('data.variants'));
        // ۲×۲ = ۴ ترکیب؛ «۴۰/سیاه» از قبل بود ← ۳ تازه + ۱ قدیمی
        $this->assertCount(4, $variants);
        $this->assertSame('10', $variants[0]['price']);
        $this->assertSame('1500', $variants[1]['price']);
        $this->assertSame('5', $variants[1]['stock_qty']);
    }

    public function test_editor_can_manage_products_but_staff_cannot(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get('/admin/products')->assertForbidden();
        $this->get('/admin/products/create')->assertForbidden();
    }

    public function test_trashed_product_can_be_restored_from_the_list(): void
    {
        $product = Product::factory()->withVariant()->create();
        $product->delete();

        $this->assertSoftDeleted($product);
        Livewire::test(ListProducts::class)
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$product])
            ->callAction(TestAction::make('restore')->table($product));

        $this->assertNotSoftDeleted($product);
    }
}
