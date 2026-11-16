<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\RelationManagers\CategoryAttributesRelationManager;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CatalogAttributeSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * مرحله‌ی ۳-۱: برندها، مشخصه‌ها با گزینه‌ها، مشخصه‌های هر دسته (با ارث‌بری).
 */
class CatalogAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_brands_attributes_and_category_links_and_is_safe_to_repeat(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $counts = [Brand::query()->count(), Attribute::query()->count(), \DB::table('attribute_options')->count(), \DB::table('category_attribute')->count()];
        $this->seed(CatalogAttributeSeeder::class);

        $this->assertSame($counts, [Brand::query()->count(), Attribute::query()->count(), \DB::table('attribute_options')->count(), \DB::table('category_attribute')->count()]);
        $this->assertSame('#000000', Attribute::query()->where('code', 'color')->firstOrFail()->options()->where('slug', 'black')->value('color_hex'));
        $this->assertTrue(Attribute::query()->where('code', 'shoe_size')->value('is_variant'));
    }

    public function test_sub_categories_inherit_attributes_and_nearest_setting_wins(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);

        $samsung = Category::query()->where('slug', 'samsung-phones')->firstOrFail();
        $codes = $samsung->effectiveAttributes()->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['model', 'storage', 'ram', 'color', 'warranty', 'condition'], $codes);

        // کفش بچه‌گانه: سایز سن از خودش + رنگ و جنس از «بوت و کفش»
        $kids = Category::query()->where('slug', 'kids-shoes')->firstOrFail();
        $this->assertContains('kids_size', $kids->effectiveAttributes()->pluck('code')->all());
        $this->assertContains('color', $kids->effectiveAttributes()->pluck('code')->all());

        // اگر همان مشخصه در زیردسته «لازم نیست» شود، تنظیم زیردسته برنده است
        $color = Attribute::query()->where('code', 'color')->firstOrFail();
        $shoes = Category::query()->where('slug', 'shoes')->firstOrFail();
        $mens = Category::query()->where('slug', 'mens-shoes')->firstOrFail();
        $mens->catalogAttributes()->attach($color->id, ['is_required' => false, 'position' => 9]);
        $this->assertTrue((bool) $shoes->effectiveAttributes()->firstWhere('code', 'color')->pivot_required);
        $this->assertFalse((bool) $mens->effectiveAttributes()->firstWhere('code', 'color')->pivot_required);
    }

    public function test_only_single_select_attributes_can_make_variants(): void
    {
        $attribute = Attribute::factory()->type('text')->create(['is_variant' => true]);

        $this->assertFalse($attribute->fresh()->is_variant);
    }

    public function test_editor_creates_an_attribute_with_options(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Editor)->create());

        Livewire::test(CreateAttribute::class)
            ->fillForm([
                'name.fa' => 'اندازه‌ی کمربند',
                'code' => 'belt_size',
                'type' => 'select',
                'is_variant' => true,
                'is_filterable' => true,
                'show_in_specs' => true,
                'is_active' => true,
                'options' => [
                    ['value' => ['fa' => '۹۰'], 'slug' => '', 'color_hex' => null],
                    ['value' => ['fa' => '۱۰۰'], 'slug' => '', 'color_hex' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $attribute = Attribute::query()->where('code', 'belt_size')->firstOrFail();
        $this->assertSame(['90', '100'], $attribute->options()->pluck('slug')->all());
    }

    public function test_attribute_code_must_be_latin(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(CreateAttribute::class)
            ->fillForm(['name.fa' => 'رنگ', 'code' => 'رنگ', 'type' => 'select'])
            ->call('create')
            ->assertHasFormErrors(['code' => 'regex']);
    }

    public function test_editor_creates_a_brand(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Editor)->create());

        Livewire::test(ManageBrands::class)
            ->callAction('create', data: ['name' => 'Infinix', 'slug' => 'Infinix', 'is_active' => true, 'position' => 1])
            ->assertHasNoActionErrors();

        $this->assertTrue(Brand::query()->where('slug', 'infinix')->exists());
    }

    public function test_attributes_are_attached_to_a_category_from_its_page(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        Livewire::test(CategoryAttributesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callTableAction('attach', data: ['recordId' => $attribute->id, 'is_required' => true, 'position' => 2])
            ->assertHasNoTableActionErrors();

        $this->assertTrue((bool) $category->catalogAttributes()->first()->pivot->is_required);
    }

    public function test_category_edit_page_shows_its_attributes_and_commission(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $phones = Category::query()->where('slug', 'mobile-phones')->firstOrFail();

        $this->actingAs(User::factory()->role(UserRole::Editor)->create())
            ->get('/admin/categories/'.$phones->id.'/edit')
            ->assertOk()
            ->assertSee(__('admin.categories.commission_rate'));   // بخش مشخصه‌ها جدا (lazy) بار می‌شود

        $this->get('/admin/categories')->assertOk()->assertSee('۳٪');
    }

    public function test_staff_cannot_manage_catalog(): void
    {
        foreach (['/admin/brands', '/admin/attributes'] as $url) {
            $this->actingAs(User::factory()->role(UserRole::Staff)->create())->get($url)->assertForbidden();
            $this->actingAs(User::factory()->role(UserRole::Editor)->create())->get($url)->assertOk();
        }
    }
}
