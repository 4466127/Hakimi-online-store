<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * اشکال صاحب پروژه: «وضعیت: نو» را در مشخصات انتخاب کردم ولی می‌گوید برای جنس فعال لازم است
 * (و اعلان خطا دو بار آمد).
 */
class ProductSpecSelectTest extends TestCase
{
    use RefreshDatabase;

    private function notifications(): int
    {
        app(NotificationMailbox::class)->flush();
        $component = new Notifications;
        $component->mount();
        $component->pullNotifications();

        return $component->notifications->count();
    }

    public function test_a_required_select_spec_chosen_in_the_form_is_saved_for_an_active_product(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $phones = Category::factory()->create(['name' => ['fa' => 'موبایل']]);
        $condition = Attribute::factory()->type('select')->create(['code' => 'condition', 'name' => ['fa' => 'وضعیت'], 'is_variant' => false]);
        $new = $condition->options()->create(['slug' => 'new', 'value' => ['fa' => 'نو']]);
        $condition->options()->create(['slug' => 'used', 'value' => ['fa' => 'کارکرده']]);
        $phones->catalogAttributes()->attach($condition->id, ['is_required' => true, 'position' => 1]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'موبایل سامسونگ A54'],
                'category_id' => $phones->id,
                'status' => 'active',
                'variants' => [['price' => '15000', 'stock_qty' => '3', 'is_active' => true]],
                // همان‌طور که مرورگر می‌فرستد: شناسه‌ی گزینه به شکل رشته
                'specs' => [$condition->id => (string) $new->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->firstOrFail();
        $this->assertSame('active', $product->status);
        $this->assertSame($new->id, $product->attributeValues()->firstOrFail()->attribute_option_id);
        $this->assertSame(0, $this->notifications() - 1, 'فقط اعلان موفقیت');
    }

    public function test_spec_fields_exist_from_the_start_and_only_show_for_categories_that_have_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $phones = Category::factory()->create(['name' => ['fa' => 'موبایل']]);
        $books = Category::factory()->create(['name' => ['fa' => 'کتاب']]);
        $condition = Attribute::factory()->type('select')->create(['name' => ['fa' => 'وضعیت'], 'is_variant' => false]);
        $condition->options()->create(['slug' => 'new', 'value' => ['fa' => 'نو']]);
        $phones->catalogAttributes()->attach($condition->id, ['is_required' => true, 'position' => 1]);

        // خانه‌ی مشخصات از اول در فورم است (نه اینکه بعد از انتخاب دسته ساخته شود)؛ فقط دیده نمی‌شود.
        // اگر بعد از انتخاب دسته ساخته می‌شد، انتخاب «نو» در مرورگر به سرور نمی‌رسید (اشکال «وضعیت لازم است»).
        $component = Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('specs.'.$condition->id)
            ->assertFormFieldIsHidden('specs.'.$condition->id)
            ->assertFormSet(['status' => 'draft', 'is_featured' => false, 'existing_images' => []])   // پیش‌فرض‌های فورم
            ->assertCount('data.variants', 1);

        $component->set('data.category_id', $phones->id)->assertFormFieldIsVisible('specs.'.$condition->id);
        $component->set('data.category_id', $books->id)->assertFormFieldIsHidden('specs.'.$condition->id);
    }

    public function test_a_missing_required_spec_gives_one_red_notification_not_two(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
        $phones = Category::factory()->create(['name' => ['fa' => 'موبایل']]);
        $condition = Attribute::factory()->type('select')->create(['name' => ['fa' => 'وضعیت'], 'is_variant' => false]);
        $condition->options()->create(['slug' => 'new', 'value' => ['fa' => 'نو']]);
        $phones->catalogAttributes()->attach($condition->id, ['is_required' => true, 'position' => 1]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['fa' => 'موبایل'],
                'category_id' => $phones->id,
                'status' => 'active',
                'variants' => [['price' => '15000', 'stock_qty' => '3', 'is_active' => true]],
            ])
            ->call('create');

        $this->assertSame(1, $this->notifications(), 'هر خطا فقط یک اعلان');
    }
}
