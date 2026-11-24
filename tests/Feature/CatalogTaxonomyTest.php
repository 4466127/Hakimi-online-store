<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CatalogAttributeSeeder;
use Database\Seeders\CatalogTaxonomySeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تکمیل زیردسته‌ها و مشخصه‌های هر زیردسته (۳ قوس ۱۴۰۵).
 */
class CatalogTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $this->seed(CatalogTaxonomySeeder::class);
    }

    /** @return list<string> */
    private function codes(string $slug): array
    {
        return Category::query()->where('slug', $slug)->firstOrFail()
            ->effectiveAttributes()->pluck('code')->all();
    }

    public function test_only_subcategories_are_added_and_running_twice_changes_nothing(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $roots = Category::query()->roots()->count();

        $this->seed(CatalogTaxonomySeeder::class);
        $counts = [Category::query()->count(), Attribute::query()->count(), AttributeOption::query()->count(), DB::table('category_attribute')->count()];

        $this->assertSame($roots, Category::query()->roots()->count());
        $energy = Category::query()->where('slug', 'energy')->sole();
        $this->assertSame('home-appliances', $energy->parent?->slug);
        $this->assertSame($energy->id, Category::query()->where('slug', 'solar-panels')->value('parent_id'));
        $this->assertSame(2, Category::query()->where('slug', 'solar-panels')->value('depth'));

        $this->seed(CatalogTaxonomySeeder::class);
        $this->assertSame($counts, [Category::query()->count(), Attribute::query()->count(), AttributeOption::query()->count(), DB::table('category_attribute')->count()]);
    }

    public function test_each_subcategory_gets_specs_that_fit_it(): void
    {
        $this->seedAll();

        // ماوس دیگر RAM و پروسسور نمی‌خواهد؛ لپتاپ می‌خواهد
        $this->assertNotContains('ram', $this->codes('mouse-keyboard'));
        $this->assertContains('keyboard_language', $this->codes('mouse-keyboard'));
        $this->assertContains('ram', $this->codes('new-laptops'));

        // موبایل: سیم‌کارت، شبکه، دوربین؛ چارجر: توان و نوع اتصال ولی نه حافظه
        $this->assertContains('sim_count', $this->codes('samsung-phones'));
        $this->assertContains('charger_power', $this->codes('chargers'));
        $this->assertNotContains('storage', $this->codes('chargers'));

        // سایز لباس فقط برای لباس، نه بکس و ساعت؛ بوت بچه سایز بچه‌گانه دارد نه سایز بزرگسال
        $this->assertContains('clothing_size', $this->codes('perahan-tunban'));
        $this->assertNotContains('clothing_size', $this->codes('womens-bags'));
        $this->assertNotContains('clothing_size', $this->codes('wrist-watches'));
        $this->assertContains('kids_shoe_size', $this->codes('boys-shoes'));
        $this->assertNotContains('shoe_size', $this->codes('boys-shoes'));
        $this->assertContains('shoe_size', $this->codes('mens-sport-shoes'));

        // بازار افغانستان: سولر/باتری، بخاری با نوع سوخت، کتاب درسی با صنف
        $this->assertContains('battery_ah', $this->codes('batteries-ups'));
        $this->assertContains('voltage', $this->codes('solar-panels'));
        $this->assertContains('fuel_type', $this->codes('gas-heaters'));
        $this->assertContains('grade', $this->codes('textbooks'));
        $this->assertContains('pack_size', $this->codes('saffron'));

        // لیست‌های انتخابی گزینه دارند
        foreach (['season', 'connection_type', 'made_in', 'fuel_type', 'grade'] as $code) {
            $attribute = Attribute::query()->where('code', $code)->sole();
            $this->assertSame('select', $attribute->type, $code);
            $this->assertGreaterThan(2, $attribute->options()->count(), $code);
        }
    }

    public function test_owner_values_are_kept_and_a_used_attribute_type_is_not_changed(): void
    {
        $this->seed(CategorySeeder::class);
        $season = Attribute::create(['code' => 'season', 'name' => ['fa' => 'فصل'], 'type' => 'text']);
        $processor = Attribute::create(['code' => 'processor', 'name' => ['fa' => 'پردازنده'], 'type' => 'number', 'unit' => 'GHz']);
        $product = Product::factory()->create(['category_id' => Category::query()->where('slug', 'new-laptops')->value('id')]);
        DB::table('product_attribute_values')->insert([
            ['product_id' => $product->id, 'attribute_id' => $season->id, 'value_text' => 'زمستان'],
            ['product_id' => $product->id, 'attribute_id' => $processor->id, 'value_text' => 'Core i7 نسل ۸'],
        ]);

        $this->seed(CatalogAttributeSeeder::class);
        $this->seed(CatalogTaxonomySeeder::class);

        // «فصل» مقدار متنی دارد: متنی می‌ماند. «پروسسور» متن داشت: متنی می‌شود و مقدار می‌ماند
        $this->assertSame('text', $season->fresh()->type);
        $this->assertSame('text', $processor->fresh()->type);
        $this->assertNull($processor->fresh()->unit);
        $this->assertSame(2, DB::table('product_attribute_values')->where('product_id', $product->id)->count());
    }

    public function test_a_missing_option_can_be_added_from_the_product_form_without_duplicates(): void
    {
        $this->seedAll();
        $warranty = Attribute::query()->where('code', 'warranty')->sole();
        $before = $warranty->options()->count();

        $id = ProductForm::addOption($warranty->id, ' ۳ سال ');
        $this->assertSame('۳ سال', AttributeOption::query()->find($id)?->tr('value'));
        $this->assertSame($id, ProductForm::addOption($warranty->id, '۳ سال'));
        $this->assertSame($before + 1, $warranty->options()->count());
    }
}
