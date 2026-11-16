<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Province;
use App\Services\Settings;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * صفحه‌های مشتری: خانه، صفحه‌های ثابت، خطاها، هدرهای امنیتی.
 */
class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_rtl_dari_and_shows_store_name(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<html lang="fa" dir="rtl">', false);
        $response->assertSee('فروشگاه برادران حکیمی');
        $response->assertSee(__('store.home.soon_heading'));
    }

    public function test_home_page_loads_nothing_from_other_websites(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/(?:src|href)="(https?:)?\/\/([^"\/]+)/', $html, $matches);
        $hosts = array_unique($matches[2]);
        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        foreach ($hosts as $host) {
            $this->assertSame($ownHost, preg_replace('/:\d+$/', '', $host), 'فایل بیرونی در صفحه: '.$host);
        }
    }

    public function test_home_page_says_delivery_is_only_in_kabul_and_ghazni_for_now(): void
    {
        $this->seed(ProvinceSeeder::class);

        $response = $this->get('/');

        $response->assertSee('تحویل فعلاً در کابل و غزنی — بقیه‌ی ولایات زیر کار است');
        $response->assertSee('کابل — '.__('store.home.delivery_active'));
        $response->assertSee('غزنی — '.__('store.home.delivery_active'));
        $response->assertSee(__('store.home.delivery_other_provinces').' — '.__('store.home.delivery_soon'));
        $response->assertDontSee('هرات — ');
    }

    public function test_turning_on_delivery_for_a_province_shows_it_on_the_site(): void
    {
        $this->seed(ProvinceSeeder::class);

        Province::query()->where('code', 'HER')->firstOrFail()->update(['delivery_enabled' => true]);

        $this->get('/')->assertSee('هرات — '.__('store.home.delivery_active'));
    }

    public function test_home_page_shows_contact_buttons_only_when_numbers_are_set(): void
    {
        $this->get('/')->assertDontSee('wa.me');

        app(Settings::class)->setMany(['store.phone' => '0700123456', 'store.whatsapp' => '0799111222']);

        $response = $this->get('/');
        $response->assertSee('https://wa.me/93799111222', false);
        $response->assertSee('tel:+93700123456', false);
        $response->assertSee('۰۷۰۰ ۱۲۳ ۴۵۶');
    }

    public function test_static_page_is_shown_with_its_text(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'title' => ['fa' => 'درباره‌ی ما'],
            'body' => ['fa' => '<p>ما یک دکان خانوادگی هستیم.</p>'],
        ]);

        $response = $this->get('/page/about');

        $response->assertOk();
        $response->assertSee('درباره‌ی ما');
        $response->assertSee('<p>ما یک دکان خانوادگی هستیم.</p>', false);
    }

    public function test_page_without_text_or_inactive_page_is_not_shown(): void
    {
        Page::factory()->empty()->create(['slug' => 'terms']);
        Page::factory()->inactive()->create(['slug' => 'hidden']);

        $this->get('/page/terms')->assertNotFound();
        $this->get('/page/hidden')->assertNotFound();
        $this->get('/page/does-not-exist')->assertNotFound();
    }

    public function test_dangerous_html_in_page_text_is_removed(): void
    {
        Page::factory()->create([
            'slug' => 'unsafe',
            'body' => ['fa' => '<p>متن خوب</p><script>alert(1)</script><img src="x" onerror="alert(2)">'],
        ]);

        $response = $this->get('/page/unsafe');

        $response->assertOk();
        $response->assertSee('متن خوب');
        $response->assertDontSee('alert(1)', false);
        $response->assertDontSee('onerror', false);
    }

    public function test_footer_lists_only_pages_that_have_text(): void
    {
        Page::factory()->create(['slug' => 'returns', 'title' => ['fa' => 'مرجوعی و ضمانت']]);
        Page::factory()->empty()->create(['slug' => 'privacy', 'title' => ['fa' => 'حریم خصوصی']]);

        $response = $this->get('/');

        $response->assertSee('مرجوعی و ضمانت');
        $response->assertDontSee('حریم خصوصی');
    }

    public function test_not_found_page_is_friendly_and_in_dari(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertSee(__('store.errors.404_title'));
        $response->assertSee(__('store.errors.back_home'));
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
