<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * عکس پیش‌فرض کاربر: یک دایره با اولین حرف نام، ساخته‌شده در خود سرور.
 * (پیش‌فرض Filament عکس را از سایت بیرونی می‌گیرد؛ ما هیچ فایل بیرونی نمی‌خواهیم.)
 */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $initial = $name === '' ? '؟' : mb_substr($name, 0, 1);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#047857"/>'
            .'<text x="32" y="43" font-size="30" text-anchor="middle" fill="#ffffff" font-family="Vazirmatn, Tahoma, sans-serif">'
            .htmlspecialchars($initial, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
