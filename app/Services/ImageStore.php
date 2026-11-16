<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ذخیره‌ی امن عکس بارگذاری‌شده.
 *
 * عکس هیچ‌وقت همان‌طور که آمده ذخیره نمی‌شود: محتوا بررسی می‌شود (نه فقط پسوند)، عکس از نو
 * ساخته می‌شود (تا هر کد پنهان داخل فایل بی‌اثر شود)، اگر بزرگ بود کوچک می‌شود و با نام
 * تصادفی به شکل WebP ذخیره می‌شود. عکس‌های اجناس در مرحله‌ی ۳ چند اندازه می‌گیرند.
 */
class ImageStore
{
    /** نوع‌های مجاز (از محتوای فایل، نه از نام آن). */
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /**
     * @param  string  $sourcePath  مسیر فایل موقت بارگذاری‌شده
     * @param  string  $directory  پوشه در دیسک (مثل «store»)
     * @param  int  $maxSide  بزرگ‌ترین ضلع به پیکسل؛ عکس بزرگ‌تر کوچک می‌شود
     * @return string مسیر فایل ذخیره‌شده در دیسک
     *
     * @throws InvalidArgumentException اگر فایل عکسِ درست نباشد
     */
    public function storeAsWebp(string $sourcePath, string $directory, int $maxSide = 1200, int $quality = 85, string $disk = 'public'): string
    {
        $bytes = @file_get_contents($sourcePath);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], self::ALLOWED, true)) {
            throw new InvalidArgumentException('Not an allowed image.');
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new InvalidArgumentException('Image could not be read.');
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // بوم تازه با زمینه‌ی شفاف (برای لوگوی PNG)
        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($target, null, $quality);
        $webp = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }

    /**
     * عکس در چند اندازه (مثل دیجی‌کالا): موبایل فقط نسخه‌ی کوچک را می‌گیرد (srcset)،
     * پس در انترنت ضعیف هم صفحه زود باز می‌شود. اندازه‌ای که از اصل عکس بزرگ‌تر باشد ساخته نمی‌شود.
     *
     * @param  list<int>  $widths  پهنای هر نسخه به پیکسل
     * @return array{path: string, sizes: array<int, string>, width: int, height: int, file_size: int}
     *
     * @throws InvalidArgumentException اگر فایل عکسِ درست نباشد
     */
    public function storeResponsive(string $sourcePath, string $directory, array $widths = [300, 600, 1200], int $quality = 80, string $disk = 'public'): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info[2], self::ALLOWED, true)) {
            throw new InvalidArgumentException('Not an allowed image.');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];

        sort($widths);
        $base = trim($directory, '/').'/'.Str::uuid();
        $sizes = [];
        foreach ($widths as $w) {
            // عکس کوچک بزرگ نمی‌شود: آخرین نسخه همان اندازه‌ی اصل است
            $target = min($w, $width);
            if (isset($sizes[$target])) {
                break;
            }
            $path = $this->storeAsWebp($sourcePath, $directory, max($target, (int) round($target * $height / max(1, $width))), $quality, $disk);
            // نام خوانا: <uuid>-600.webp
            $named = $base.'-'.$target.'.webp';
            Storage::disk($disk)->move($path, $named);
            $sizes[$target] = $named;
        }

        $largest = end($sizes);

        return [
            'path' => (string) $largest,
            'sizes' => $sizes,
            'width' => $width,
            'height' => $height,
            'file_size' => (int) Storage::disk($disk)->size((string) $largest),
        ];
    }

    /** فایل قدیمی را پاک می‌کند (اگر بود). */
    public function delete(?string $path, string $disk = 'public'): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }
}
