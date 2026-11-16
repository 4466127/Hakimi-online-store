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
 * ساخته می‌شود (تا هر کد پنهان، معلومات EXIF و موقعیت داخل فایل بی‌اثر شود)، اگر بزرگ بود کوچک
 * می‌شود و با نام تصادفی به شکل WebP ذخیره می‌شود. عکس جنس چند اندازه می‌گیرد و (اگر سرور بتواند)
 * از هر اندازه یک نسخه‌ی AVIF هم ساخته می‌شود که حدود ۲۰٪ از WebP کوچک‌تر است.
 *
 * عکسی که از قبل WebP یا AVIF است هم دوباره ساخته می‌شود: برای ساختن اندازه‌های کوچک‌تر (موبایل) و
 * مهم‌تر از آن برای پاک شدن کد پنهان داخل فایل. این کار چند دهم ثانیه است و دیده نمی‌شود.
 */
class ImageStore
{
    /** کیفیت AVIF (پایین‌تر از WebP چون AVIF در همان کیفیت کوچک‌تر است) و سرعت رمزگذاری (۰ کند‌ترین، ۹ سریع‌ترین). */
    private const AVIF_QUALITY = 55;

    private const AVIF_SPEED = 6;

    /** سرور می‌تواند AVIF بخواند و بسازد؟ (به نسخه‌ی GD سرور بستگی دارد؛ پیش از استفاده بررسی می‌شود.) */
    public function supportsAvif(): bool
    {
        return function_exists('imageavif') && function_exists('imagecreatefromavif') && defined('IMAGETYPE_AVIF');
    }

    /**
     * نوع‌های مجاز (از محتوای فایل، نه از نام آن): JPG، PNG، WebP و (اگر سرور بتواند) AVIF.
     *
     * @return list<int>
     */
    private function allowedTypes(): array
    {
        $types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if ($this->supportsAvif()) {
            $types[] = IMAGETYPE_AVIF;
        }

        return $types;
    }

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
        if ($info === false || ! in_array($info[2], $this->allowedTypes(), true)) {
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
     * @param  bool  $withAvif  از هر اندازه نسخه‌ی AVIF هم ساخته شود (فقط اگر سرور پشتیبانی کند)
     * @return array{path: string, sizes: array<int, string>, avif: array<int, string>, width: int, height: int, file_size: int}
     *
     * @throws InvalidArgumentException اگر فایل عکسِ درست نباشد
     */
    public function storeResponsive(string $sourcePath, string $directory, array $widths = [300, 600, 1200], int $quality = 80, string $disk = 'public', bool $withAvif = false): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info[2], $this->allowedTypes(), true)) {
            throw new InvalidArgumentException('Not an allowed image.');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];

        sort($widths);
        $base = trim($directory, '/').'/'.Str::uuid();
        $withAvif = $withAvif && $this->supportsAvif();
        $sizes = [];
        $avif = [];
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

            if ($withAvif) {
                $avifPath = $this->storeAsAvif($sourcePath, $directory, max($target, (int) round($target * $height / max(1, $width))), self::AVIF_QUALITY, $disk);
                $avifNamed = $base.'-'.$target.'.avif';
                Storage::disk($disk)->move($avifPath, $avifNamed);
                $avif[$target] = $avifNamed;
            }
        }

        $largest = end($sizes);

        return [
            'path' => (string) $largest,
            'sizes' => $sizes,
            'avif' => $avif,
            'width' => $width,
            'height' => $height,
            'file_size' => (int) Storage::disk($disk)->size((string) $largest),
        ];
    }

    /**
     * مثل storeAsWebp ولی خروجی AVIF (فقط وقتی supportsAvif() درست باشد).
     *
     * @throws InvalidArgumentException اگر فایل عکسِ درست نباشد یا سرور AVIF ندارد
     */
    public function storeAsAvif(string $sourcePath, string $directory, int $maxSide = 1200, int $quality = self::AVIF_QUALITY, string $disk = 'public'): string
    {
        if (! $this->supportsAvif()) {
            throw new InvalidArgumentException('AVIF is not supported on this server.');
        }
        $bytes = @file_get_contents($sourcePath);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], $this->allowedTypes(), true)) {
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

        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imageavif($target, null, $quality, self::AVIF_SPEED);
        $avif = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::uuid().'.avif';
        Storage::disk($disk)->put($path, $avif);

        return $path;
    }

    /** فایل قدیمی را پاک می‌کند (اگر بود). */
    public function delete(?string $path, string $disk = 'public'): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }
}
