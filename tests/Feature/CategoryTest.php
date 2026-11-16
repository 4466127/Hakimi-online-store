<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryMenu;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * دسته‌بندی اجناس: درخت سه‌سطحی، منو، صفحه‌ی دسته و پنل.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Category, 1: Category, 2: Category} */
    private function tree(): array
    {
        $root = Category::factory()->create(['slug' => 'mobile', 'name' => ['fa' => 'موبایل']]);
        $group = Category::factory()->childOf($root)->create(['slug' => 'accessories', 'name' => ['fa' => 'لوازم جانبی']]);
        $item = Category::factory()->childOf($group)->create(['slug' => 'chargers', 'name' => ['fa' => 'چارجر']]);

        return [$root, $group, $item];
    }

    public function test_depth_and_path_are_calculated(): void
    {
        [$root, $group, $item] = $this->tree();

        $this->assertSame([0, ''], [$root->depth, $root->path]);
        $this->assertSame([1, (string) $root->id], [$group->depth, $group->path]);
        $this->assertSame([2, $root->id.'/'.$group->id], [$item->depth, $item->path]);
        $this->assertSame([$root->id, $group->id], $item->ancestorIds());
        $this->assertEqualsCanonicalizing([$group->id, $item->id], $root->descendantIds());
    }

    public function test_tree_cannot_be_deeper_than_three_levels(): void
    {
        [, , $item] = $this->tree();

        $this->expectException(LogicException::class);
        Category::factory()->childOf($item)->create();
    }

    public function test_category_cannot_move_under_its_own_child(): void
    {
        [$root, $group] = $this->tree();

        $this->expectException(LogicException::class);
        $root->update(['parent_id' => $group->id]);
    }

    public function test_moving_a_group_updates_its_children(): void
    {
        [, $group, $item] = $this->tree();
        $otherRoot = Category::factory()->create();

        $group->update(['parent_id' => $otherRoot->id]);

        $this->assertSame($otherRoot->id.'/'.$group->id, $item->fresh()->path);
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        [$root] = $this->tree();

        $this->expectException(LogicException::class);
        $root->delete();
    }

    public function test_menu_hides_inactive_branches_and_items_not_in_menu(): void
    {
        [$root, $group] = $this->tree();
        $hiddenRoot = Category::factory()->inactive()->create(['slug' => 'hidden']);
        Category::factory()->childOf($hiddenRoot)->create(['slug' => 'under-hidden']);
        Category::factory()->create(['slug' => 'not-in-menu', 'show_in_menu' => false]);

        $menu = app(CategoryMenu::class)->menu();
        $this->assertSame(['mobile'], array_column($menu, 'slug'));
        $this->assertSame('accessories', $menu[0]['children'][0]['slug']);
        $this->assertSame('chargers', $menu[0]['children'][0]['children'][0]['slug']);

        // تغییر دسته cache منو را پاک می‌کند
        $group->update(['is_active' => false]);
        $this->app->forgetInstance(CategoryMenu::class);
        $this->assertSame([], app(CategoryMenu::class)->menu()[0]['children']);
    }

    public function test_mega_menu_and_category_pages_show_the_tree(): void
    {
        [$root, $group, $item] = $this->tree();

        $this->get('/')->assertOk()
            ->assertSee(__('store.nav.all_categories'))
            ->assertSee('لوازم جانبی')
            ->assertSee(route('category.show', ['slug' => 'chargers']), false);

        $this->get('/categories')->assertOk()->assertSee('موبایل')->assertSee('چارجر');

        $this->get('/category/chargers')->assertOk()
            ->assertSee('موبایل')          // مسیر صفحه
            ->assertSee('لوازم جانبی')
            ->assertSee(__('store.categories.products_soon_heading'));

        $this->get('/category/mobile')->assertOk()->assertSee('لوازم جانبی');
    }

    public function test_inactive_category_and_its_children_are_not_found(): void
    {
        [$root] = $this->tree();
        $root->update(['is_active' => false]);

        $this->get('/category/mobile')->assertNotFound();
        $this->get('/category/chargers')->assertNotFound();
        $this->get('/category/nothing')->assertNotFound();
    }

    public function test_seeder_creates_the_main_categories_and_is_safe_to_repeat(): void
    {
        $this->seed(CategorySeeder::class);
        $count = Category::query()->count();
        $this->seed(CategorySeeder::class);

        $this->assertSame($count, Category::query()->count());
        $this->assertSame(['mobile', 'computers', 'digital', 'shoes', 'clothing'], array_slice(Category::query()->roots()->orderBy('position')->pluck('slug')->all(), 0, 5));
        $this->assertSame('3.00', Category::query()->where('slug', 'mobile-phones')->value('commission_rate'));
        $this->assertFalse(Category::query()->whereIn('slug', ['cars', 'gold', 'tools', 'health'])->exists());
        $this->assertSame(2, Category::query()->where('slug', 'chargers')->value('depth'));

        // کلمه‌های افغانستانی و دسته‌های تازه (۲۶ عقرب ۱۴۰۵)
        $this->assertSame('لباس هزارگی و افغانی', Category::query()->where('slug', 'clothing')->first()->tr('name'));
        $this->assertSame('بکس زنانه', Category::query()->where('slug', 'womens-bags')->first()->tr('name'));
        $this->assertTrue(Category::query()->roots()->where('slug', 'books-art')->exists());
        $this->assertTrue(Category::query()->roots()->where('slug', 'sports')->exists());
        $this->assertSame(11, Category::query()->roots()->count());
    }

    public function test_editor_creates_a_sub_category_from_the_panel(): void
    {
        $root = Category::factory()->create(['slug' => 'shoes', 'name' => ['fa' => 'کفش']]);
        $this->actingAs(User::factory()->role(UserRole::Editor)->create());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name.fa' => 'کفش مردانه',
                'parent_id' => $root->id,
                'slug' => 'Mens Shoes',
                'is_active' => true,
                'show_in_menu' => true,
                'position' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'mens-shoes')->firstOrFail();
        $this->assertSame(1, $category->depth);
        $this->assertTrue(ActivityLog::query()->where('action', 'category.created')->where('subject_id', $category->id)->exists());
    }
}
