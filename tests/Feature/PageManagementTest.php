<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صفحه‌های ثابت در پنل: ساختن و ویرایش توسط ویرایشگر.
 */
class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(UserRole::Editor)->create());
    }

    public function test_editor_creates_a_page_and_customers_see_it(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title.fa' => 'شرایط خرید',
                'slug' => 'Shopping Terms',
                'body.fa' => '<p>جنس را هنگام تحویل ببینید.</p>',
                'is_active' => true,
                'show_in_footer' => true,
                'position' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // آدرس یک‌شکل می‌شود: حروف خرد و خط تیره
        $page = Page::query()->where('slug', 'shopping-terms')->firstOrFail();
        $this->assertSame('شرایط خرید', $page->tr('title'));
        $this->assertTrue(ActivityLog::query()->where('action', 'page.created')->where('subject_id', $page->id)->exists());

        $this->get('/page/shopping-terms')->assertOk()->assertSee('جنس را هنگام تحویل ببینید.');
        $this->get('/')->assertSee('شرایط خرید');
    }

    public function test_title_and_address_are_required_and_address_is_unique(): void
    {
        Page::factory()->create(['slug' => 'about']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title.fa' => '', 'slug' => ''])
            ->call('create')
            ->assertHasFormErrors(['title.fa', 'slug']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title.fa' => 'درباره', 'slug' => 'About'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_editing_a_page_updates_the_site_immediately(): void
    {
        $page = Page::factory()->create(['slug' => 'about', 'title' => ['fa' => 'درباره‌ی ما']]);
        // یک بار صفحه‌ی اصلی دیده شود تا لیست پاورقی cache شود
        $this->get('/')->assertSee('درباره‌ی ما');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title.fa' => 'معرفی فروشگاه'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/')->assertSee('معرفی فروشگاه')->assertDontSee('درباره‌ی ما');
    }

    public function test_translation_falls_back_to_dari(): void
    {
        $page = Page::factory()->make(['title' => ['fa' => 'تماس', 'en' => 'Contact']]);

        $this->assertSame('تماس', $page->tr('title'));
        $this->assertSame('Contact', $page->tr('title', 'en'));
        // پشتو نداریم: دری نشان داده می‌شود
        $this->assertSame('تماس', $page->tr('title', 'ps'));
    }
}
