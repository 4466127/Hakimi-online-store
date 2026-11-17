<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * فایل‌های موقت بارگذاری عکس جنس (`tmp/product-uploads` روی دیسک local) را که بیشتر از ۲ روز رها شده پاک می‌کند.
 * فایل موقت وقتی می‌ماند که فورم جنس خطا داد و مدیر آن را رها کرد (نگه‌داشتن آن عمدی است: مدیر عکس را دوباره نمی‌فرستد).
 */
class PruneTempUploads extends Command
{
    protected $signature = 'uploads:prune-temp {--days=2 : فایل‌های قدیمی‌تر از این تعداد روز پاک می‌شود}';

    protected $description = 'پاک کردن فایل‌های موقت رهاشده‌ی عکس اجناس';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $limit = now()->subDays(max(1, (int) $this->option('days')))->getTimestamp();
        $removed = 0;

        foreach ($disk->files('tmp/product-uploads') as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $removed++;
            }
        }

        $this->info("فایل موقت پاک‌شده: {$removed}");

        return self::SUCCESS;
    }
}
