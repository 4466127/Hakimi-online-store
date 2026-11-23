<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مرحله‌ی ۹-۱: sitemap.xml، robots.txt و noindex صفحه‌های خصوصی.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function sitemap(): string
    {
        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));

        return (string) $response->getContent();
    }

    public function test_sitemap_lists_only_public_products_categories_and_pages(): void
    {
        $category = Category::factory()->create(['slug' => 'boot']);
        $hiddenCategory = Category::factory()->create(['slug' => 'hidden-cat', 'is_active' => false]);
        $childOfHidden = Category::factory()->create(['slug' => 'child-of-hidden', 'parent_id' => $hiddenCategory->id]);

        Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'public-boot']);
        Product::factory()->create(['category_id' => $category->id, 'slug' => 'draft-boot']);
        Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'future-boot', 'published_at' => now()->addDay()]);
        Product::factory()->active()->create(['category_id' => $childOfHidden->id, 'slug' => 'in-hidden-cat']);
        Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'deleted-boot'])->delete();

        $pending = Seller::factory()->status(SellerStatus::Pending)->create();
        $approved = Seller::factory()->status(SellerStatus::Approved)->create();
        Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'pending-seller', 'seller_id' => $pending->id]);
        Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'approved-seller', 'seller_id' => $approved->id]);

        Page::factory()->create(['slug' => 'about-us', 'is_active' => true]);
        Page::factory()->create(['slug' => 'secret-page', 'is_active' => false]);

        $xml = $this->sitemap();

        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString(route('home'), $xml);
        $this->assertStringContainsString('/category/boot</loc>', $xml);
        $this->assertStringContainsString('/p/public-boot</loc>', $xml);
        $this->assertStringContainsString('/p/approved-seller</loc>', $xml);
        $this->assertStringContainsString('/page/about-us</loc>', $xml);

        foreach (['draft-boot', 'future-boot', 'in-hidden-cat', 'deleted-boot', 'pending-seller', 'hidden-cat', 'child-of-hidden', 'secret-page'] as $hidden) {
            $this->assertStringNotContainsString('/'.$hidden.'</loc>', $xml, $hidden);
        }
        $this->assertNotFalse(simplexml_load_string($xml), 'XML درست نیست');
    }

    public function test_sitemap_refreshes_when_products_change(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->active()->create(['category_id' => $category->id, 'slug' => 'first-name']);

        $this->assertStringContainsString('/p/first-name<', $this->sitemap());

        $product->update(['status' => 'draft']);
        $this->assertStringNotContainsString('/p/first-name<', $this->sitemap());

        $product->update(['status' => 'active']);
        $this->assertStringContainsString('/p/first-name<', $this->sitemap());

        $product->delete();
        $this->assertStringNotContainsString('/p/first-name<', $this->sitemap());
    }

    public function test_robots_txt_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = (string) $response->getContent();
        foreach (['/admin', '/cart', '/checkout', '/account', '/order/', '/track', '/login', '/register', '/sell/onboarding'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path."\n", $body);
        }
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body, 'کل سایت نباید بسته شود');
    }

    public function test_private_pages_send_noindex_header_and_public_pages_do_not(): void
    {
        $this->get('/cart')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/checkout')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        // صفحه‌ی خصوصی هنوز با ورود محافظت می‌شود؛ robots امنیت نیست
        $this->get('/account')->assertRedirect(route('login'));

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/categories')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_product_and_category_pages_have_title_description_and_canonical(): void
    {
        $category = Category::factory()->create(['slug' => 'boot', 'name' => ['fa' => 'بوت']]);
        $product = Product::factory()->active()->withVariant()->create([
            'category_id' => $category->id, 'slug' => 'leather-boot', 'name' => ['fa' => 'بوت چرمی'],
        ]);

        $this->get($product->url())->assertOk()
            ->assertSee('<title>بوت چرمی | ', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical" href="'.$product->url().'">', false);

        $this->get($category->url())->assertOk()
            ->assertSee('<title>بوت | ', false)
            ->assertSee('<link rel="canonical" href="'.$category->url().'">', false);
    }
}
