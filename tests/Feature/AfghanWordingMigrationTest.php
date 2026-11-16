<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Province;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * migration تصمیم‌های ۲۶ عقرب ۱۴۰۵: کلمه‌های افغانستانی، برداشتن «سلامت و پزشکی»، تحویل فقط در کابل.
 * داده‌ی قدیمی (نام‌های ایرانی) را می‌سازد و migration را دوباره روی آن اجرا می‌کند.
 */
class AfghanWordingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_000100_afghan_wording_and_kabul_only_delivery.php');
    }

    public function test_old_names_are_renamed_but_names_edited_by_the_admin_are_kept(): void
    {
        $clothing = Category::factory()->create(['slug' => 'clothing', 'name' => ['fa' => 'لباس افغانی و مُد']]);
        $bags = Category::factory()->create(['slug' => 'bags-accessories', 'name' => ['fa' => 'کیف و اکسسوری'], 'parent_id' => $clothing->id]);
        $edited = Category::factory()->create(['slug' => 'digital', 'name' => ['fa' => 'دیجیتال من']]);
        $page = Page::factory()->create(['slug' => 'returns', 'title' => ['fa' => 'مرجوعی و ضمانت']]);

        $this->migration()->up();

        $this->assertSame('لباس هزارگی و افغانی', $clothing->fresh()->tr('name'));
        $this->assertSame('بکس و اکسسوری', $bags->fresh()->tr('name'));
        $this->assertSame('دیجیتال من', $edited->fresh()->tr('name'));
        $this->assertSame('برگشت جنس و ضمانت', $page->fresh()->tr('title'));

        // برگشت‌پذیر است
        $this->migration()->down();
        $this->assertSame('لباس افغانی و مُد', $clothing->fresh()->tr('name'));
    }

    public function test_health_tree_goes_to_trash_and_other_categories_stay(): void
    {
        $health = Category::factory()->create(['slug' => 'health']);
        $group = Category::factory()->create(['slug' => 'home-medical', 'parent_id' => $health->id]);
        $item = Category::factory()->create(['slug' => 'thermometers', 'parent_id' => $group->id]);
        $other = Category::factory()->create(['slug' => 'beauty']);

        $this->migration()->up();

        $this->assertSoftDeleted($health);
        $this->assertSoftDeleted($group);
        $this->assertSoftDeleted($item);
        $this->assertNotSoftDeleted($other);
        $this->assertSame(['beauty'], Category::query()->pluck('slug')->all());
    }

    public function test_delivery_is_turned_off_in_ghazni(): void
    {
        $this->seed(ProvinceSeeder::class);
        Province::query()->where('code', 'GHA')->update(['delivery_enabled' => true]);

        $this->migration()->up();

        $this->assertSame(['کابل'], Province::deliverableNames());
    }
}
