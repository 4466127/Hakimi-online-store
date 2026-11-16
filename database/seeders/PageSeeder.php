<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * صفحه‌های ثابت خالی. مدیر متن هر کدام را از پنل می‌نویسد؛ صفحه‌ی بی‌متن به مشتری نشان داده نمی‌شود.
 * چند بار اجرا کردنش بی‌خطر است: صفحه‌ی موجود دست نمی‌خورد.
 */
class PageSeeder extends Seeder
{
    private const PAGES = [
        ['about', 'درباره‌ی ما'],
        ['contact', 'تماس با ما'],
        ['terms', 'شرایط و قوانین خرید'],
        ['returns', 'برگشت جنس و ضمانت'],
        ['privacy', 'حریم خصوصی'],
    ];

    public function run(): void
    {
        // بی رویداد: داده‌ی اولیه در تاریخچه‌ی فعالیت نوشته نشود
        Page::withoutEvents(function (): void {
            foreach (self::PAGES as $index => [$slug, $title]) {
                Page::query()->firstOrCreate(['slug' => $slug], [
                    'title' => ['fa' => $title],
                    'body' => ['fa' => ''],
                    'is_active' => true,
                    'show_in_footer' => true,
                    'position' => ($index + 1) * 10,
                ]);
            }
        });
    }
}
