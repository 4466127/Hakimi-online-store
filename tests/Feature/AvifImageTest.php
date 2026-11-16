<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductImageService;
use App\Services\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * عکس جنس: WebP در سه اندازه + (اگر سرور بتواند) AVIF؛ ورودی AVIF هم قبول است.
 */
class AvifImageTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<UploadedFile> */
    private array $fakes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(ImageStore::class)->supportsAvif()) {
            $this->markTestSkipped('GD این سرور AVIF ندارد؛ سایت خودکار فقط WebP می‌سازد.');
        }
    }

    private function jpeg(int $w = 1400, int $h = 900): string
    {
        return ($this->fakes[] = UploadedFile::fake()->image('a.jpg', $w, $h))->getRealPath();
    }

    private function avifFile(int $w = 800, int $h = 600): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 40));
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), (int) ($w / 2), (int) ($h / 2), imagecolorallocate($im, 20, 90, 160));
        $path = tempnam(sys_get_temp_dir(), 'avif').'.avif';
        imageavif($im, $path, 60, 6);
        $this->assertSame(IMAGETYPE_AVIF, getimagesize($path)[2], 'فایل آزمایشی واقعاً AVIF است');

        return $path;
    }

    public function test_responsive_store_makes_an_avif_next_to_every_webp_size_and_it_is_smaller(): void
    {
        Storage::fake('public');
        $stored = app(ImageStore::class)->storeResponsive($this->jpeg(), 'products/x', withAvif: true);

        $this->assertSame([300, 600, 1200], array_keys($stored['sizes']));
        $this->assertSame([300, 600, 1200], array_keys($stored['avif']));
        foreach ($stored['avif'] as $width => $path) {
            $this->assertStringEndsWith('-'.$width.'.avif', $path);
            Storage::disk('public')->assertExists($path);
            $file = Storage::disk('public')->path($path);
            $this->assertSame(IMAGETYPE_AVIF, getimagesize($file)[2]);
            $this->assertSame($width, getimagesize($file)[0]);
        }
    }

    public function test_avif_is_not_built_unless_asked(): void
    {
        Storage::fake('public');

        $this->assertSame([], app(ImageStore::class)->storeResponsive($this->jpeg(), 'products/x')['avif']);
    }

    public function test_an_avif_upload_is_accepted_and_converted_to_webp_sizes(): void
    {
        Storage::fake('public');
        $stored = app(ImageStore::class)->storeResponsive($this->avifFile(), 'products/x', withAvif: true);

        $this->assertSame([300, 600, 800], array_keys($stored['sizes']));   // عکس کوچک بزرگ نمی‌شود
        foreach ($stored['sizes'] as $path) {
            $this->assertStringEndsWith('.webp', $path);
            $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('public')->path($path))[2]);
        }
    }

    public function test_a_file_pretending_to_be_an_image_is_still_refused(): void
    {
        Storage::fake('public');
        $fake = $this->fakes[] = UploadedFile::fake()->createWithContent('x.avif', '<?php echo 1;');

        $this->expectException(\InvalidArgumentException::class);
        app(ImageStore::class)->storeResponsive($fake->getRealPath(), 'products/x', withAvif: true);
    }

    public function test_product_images_keep_avif_sizes_render_picture_and_are_deleted_together(): void
    {
        Storage::fake('public');
        $product = Product::factory()->active()->withVariant()->create(['category_id' => Category::factory()]);
        $image = app(ProductImageService::class)->add($product, $this->jpeg());

        $this->assertSame([300, 600, 1200], array_map('intval', array_keys($image->avif_sizes)));
        $this->assertStringContainsString('300w', $image->avifSrcset());
        $this->assertStringContainsString('.avif', $image->avifSrcset());

        $page = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('type="image/avif"', $page);

        $this->get($product->url())->assertOk()
            ->assertSee('<picture', false)
            ->assertSee('type="image/avif"', false)
            ->assertSee('-600.webp', false);   // مرورگر قدیمی همان WebP را می‌گیرد

        $files = $image->files();
        $this->assertCount(6, $files);   // سه WebP + سه AVIF
        app(ProductImageService::class)->remove($image);
        foreach ($files as $file) {
            Storage::disk('public')->assertMissing($file);
        }
    }

    public function test_avif_can_be_turned_off_from_config(): void
    {
        Storage::fake('public');
        config(['store.images.avif' => false]);
        $product = Product::factory()->active()->withVariant()->create(['category_id' => Category::factory()]);

        $image = app(ProductImageService::class)->add($product, $this->jpeg());

        $this->assertNull($image->avif_sizes);
        $this->assertSame('', $image->avifSrcset());
        $this->get($product->url())->assertOk()->assertDontSee('type="image/avif"', false);
    }
}
