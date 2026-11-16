<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogAttributeSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSpecSeededTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_seeded_phone_category_saves_with_step_by_step_field_updates(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CatalogAttributeSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));

        $category = Category::query()->where('slug', 'samsung-phones')->firstOrFail();
        $opt = fn (string $code, string $slug): int => AttributeOption::query()->whereHas('attribute', fn ($q) => $q->where('code', $code))->where('slug', $slug)->value('id');
        $model = Attribute::query()->where('code', 'model')->value('id');
        $condition = Attribute::query()->where('code', 'condition')->value('id');
        $storage = Attribute::query()->where('code', 'storage')->value('id');
        $color = Attribute::query()->where('code', 'color')->value('id');

        $storageOption = AttributeOption::query()->where('attribute_id', $storage)->orderBy('position')->value('id');
        $colorOption = AttributeOption::query()->where('attribute_id', $color)->orderBy('position')->value('id');
        $new = AttributeOption::query()->where('attribute_id', $condition)->orderBy('position')->first();

        $component = Livewire::test(CreateProduct::class)
            ->set('data.name.fa', 'موبایل سامسونگ A54')
            ->set('data.category_id', $category->id)            // live: خانه‌های مشخصات تازه ساخته می‌شود
            ->set('data.status', 'active')
            ->set('data.specs.'.$model, 'A54')
            ->set('data.specs.'.$condition, $new->id)            // انتخاب «نو» (عدد، مثل مرورگر)
            ->set('data.variants', [['price' => '15000', 'stock_qty' => '3', 'is_active' => true, 'option_ids' => [$storageOption, $colorOption]]]);

        $this->assertEquals($new->id, $component->get('data.specs.'.$condition), 'مقدار در حالت فورم مانده است');

        $component->call('create')->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();
        $this->assertSame(2, $product->attributeValues()->count(), 'مودل و وضعیت ذخیره شد');
    }
}
