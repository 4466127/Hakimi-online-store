<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * مرحله‌ی ۹-۴: بنرهای صفحه‌ی اصلی — روشن/خاموش، ترتیب، سقف ۵، عکس WebP، لینک امن، بی چرخش خودکار.
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function banner(array $attributes = []): Banner
    {
        Storage::disk('public')->put($path = 'banners/'.uniqid().'.webp', 'x');

        return Banner::query()->create($attributes + ['image_path' => $path, 'position' => 1, 'is_active' => true]);
    }

    public function test_home_shows_only_active_banners_in_display_order_and_rotates_them(): void
    {
        $this->banner(['title' => ['fa' => 'بنر دوم'], 'position' => 2, 'link_url' => '/category/boot']);
        $this->banner(['title' => ['fa' => 'بنر اول'], 'position' => 1]);
        $this->banner(['title' => ['fa' => 'بنر خاموش'], 'position' => 0, 'is_active' => false]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('alt="بنر اول"', $html);
        $this->assertStringContainsString('href="/category/boot"', $html);
        $this->assertStringNotContainsString('بنر خاموش', $html);
        $this->assertLessThan(strpos($html, 'alt="بنر دوم"'), strpos($html, 'alt="بنر اول"'));
        $this->assertStringContainsString('snap-x', $html);                 // ورق زدن دستی هم هست
        $this->assertStringContainsString('setInterval', $html);            // چرخش خودکار
        $this->assertStringContainsString('interval: 1500', $html);         // هر ۱٫۵ ثانیه
        $this->assertStringContainsString('% this.count', $html);           // حلقوی: بعد از آخری، اولی
        $this->assertStringContainsString('prefers-reduced-motion', $html); // «حرکت کمتر» دستگاه رعایت می‌شود
        $this->assertStringContainsString(__('store.home.banner_go', ['n' => '۲']), $html); // نقطه‌ی هر بنر
        $this->assertStringContainsString(__('store.home.banner_next'), $html);             // دکمه‌های گوشه
    }

    public function test_home_without_banners_has_no_banner_area(): void
    {
        $this->get('/')->assertOk()->assertDontSee(__('store.home.banners_label'));
    }

    public function test_at_most_five_banners_can_be_active(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->banner(['position' => $i]);
        }
        $off = $this->banner(['is_active' => false, 'position' => 6]);

        $this->expectException(LogicException::class);
        $off->update(['is_active' => true]);
    }

    public function test_editor_uploads_a_banner_as_webp_and_the_sixth_active_one_is_refused_with_a_message(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));

        Livewire::test(CreateBanner::class)
            ->fillForm([
                'image_path' => [UploadedFile::fake()->image('eid.jpg', 1800, 700)],
                'title.fa' => 'تخفیف عید',
                'link_url' => '/category/boot',
                'position' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::query()->sole();
        $this->assertStringEndsWith('.webp', $banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($banner->image_path));
        $this->assertLessThanOrEqual(1600, $w);

        for ($i = 2; $i <= 5; $i++) {
            $this->banner(['position' => $i]);
        }
        Livewire::test(CreateBanner::class)
            ->fillForm([
                'image_path' => [UploadedFile::fake()->image('six.jpg', 1600, 600)],
                'position' => 6,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['is_active']);
        $this->assertSame(5, Banner::query()->count());
    }

    public function test_dangerous_links_are_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $banner = $this->banner();

        foreach (['javascript:alert(1)', '//evil.example', 'data:text/html,x'] as $bad) {
            Livewire::test(EditBanner::class, ['record' => $banner->getRouteKey()])
                ->fillForm(['link_url' => $bad])
                ->call('save')
                ->assertHasFormErrors(['link_url']);
        }

        Livewire::test(EditBanner::class, ['record' => $banner->getRouteKey()])
            ->fillForm(['link_url' => 'https://www.instagram.com/bazarak'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($banner->refresh()->isExternal());
    }

    public function test_deleting_or_replacing_a_banner_removes_the_old_image(): void
    {
        $banner = $this->banner();
        $old = $banner->image_path;
        Storage::disk('public')->put('banners/new.webp', 'y');

        $banner->update(['image_path' => 'banners/new.webp']);
        Storage::disk('public')->assertMissing($old);

        $banner->delete();
        Storage::disk('public')->assertMissing('banners/new.webp');
    }

    public function test_only_content_roles_manage_banners(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get(BannerResource::getUrl('index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->get(BannerResource::getUrl('index'))->assertOk()->assertSee(__('admin.banners.plural'));
    }
}
