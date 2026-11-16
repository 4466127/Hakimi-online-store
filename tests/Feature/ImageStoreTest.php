<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ImageStore;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * ذخیره‌ی امن عکس: فقط عکس واقعی، بازسازی به WebP، کوچک کردن، نام تصادفی.
 */
class ImageStoreTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->tmp = (string) tempnam(sys_get_temp_dir(), 'img');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);

        parent::tearDown();
    }

    public function test_image_is_rebuilt_as_webp_resized_and_given_a_random_name(): void
    {
        $image = imagecreatetruecolor(2000, 1000);
        imagepng($image, $this->tmp);

        $path = app(ImageStore::class)->storeAsWebp($this->tmp, 'store', maxSide: 600);

        $this->assertMatchesRegularExpression('#^store/[0-9a-f-]{36}\.webp$#', $path);
        Storage::disk('public')->assertExists($path);

        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
        $this->assertSame([600, 300], [$info[0], $info[1]]);
    }

    public function test_small_image_is_not_enlarged(): void
    {
        imagejpeg(imagecreatetruecolor(120, 80), $this->tmp);

        $path = app(ImageStore::class)->storeAsWebp($this->tmp, 'store', maxSide: 600);

        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([120, 80], [$info[0], $info[1]]);
    }

    public function test_file_that_is_not_an_image_is_rejected(): void
    {
        // فایل PHP با پسوند عکس هم رد می‌شود، چون محتوا بررسی می‌شود نه نام
        file_put_contents($this->tmp, '<?php echo "hacked"; ?>');

        $this->expectException(InvalidArgumentException::class);
        app(ImageStore::class)->storeAsWebp($this->tmp, 'store');
    }

    public function test_gif_is_not_accepted(): void
    {
        imagegif(imagecreatetruecolor(10, 10), $this->tmp);

        $this->expectException(InvalidArgumentException::class);
        app(ImageStore::class)->storeAsWebp($this->tmp, 'store');
    }

    public function test_delete_removes_the_file(): void
    {
        imagepng(imagecreatetruecolor(10, 10), $this->tmp);
        $store = app(ImageStore::class);
        $path = $store->storeAsWebp($this->tmp, 'store');

        $store->delete($path);
        $store->delete(null); // بی خطا

        Storage::disk('public')->assertMissing($path);
    }

    public function test_responsive_image_gets_small_medium_and_large_webp_versions(): void
    {
        $image = imagecreatetruecolor(1600, 1200);
        imagejpeg($image, $this->tmp);

        $result = app(ImageStore::class)->storeResponsive($this->tmp, 'products');

        $this->assertSame([300, 600, 1200], array_keys($result['sizes']));
        foreach ($result['sizes'] as $width => $path) {
            $this->assertStringEndsWith('-'.$width.'.webp', $path);
            $info = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertSame([IMAGETYPE_WEBP, $width], [$info[2], $info[0]]);
        }
        $this->assertSame([1600, 1200], [$result['width'], $result['height']]);
        $this->assertSame($result['sizes'][1200], $result['path']);
    }

    public function test_responsive_small_image_is_not_enlarged(): void
    {
        $image = imagecreatetruecolor(500, 500);
        imagepng($image, $this->tmp);

        $result = app(ImageStore::class)->storeResponsive($this->tmp, 'products');

        $this->assertSame([300, 500], array_keys($result['sizes']));
    }
}
