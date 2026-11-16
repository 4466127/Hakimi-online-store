<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * ۳۴ ولایت افغانستان (کد = کد استاندارد ISO 3166-2:AF).
 *
 * چند بار اجرا کردنش بی‌خطر است: ولایتِ موجود دوباره ساخته نمی‌شود و چیزی که مدیر
 * از پنل عوض کرده (فعال بودن، تحویل) دست نمی‌خورد.
 */
class ProvinceSeeder extends Seeder
{
    /** ولایت‌هایی که از روز اول تحویل در آن‌ها فعال است (فعلاً فقط کابل). بقیه «زیر کار است». */
    private const DELIVERY_FROM_START = ['KAB'];

    /** اول شهرهای کلان، بعد بقیه به ترتیب الفبا. */
    private const PROVINCES = [
        ['KAB', 'کابل'],
        ['HER', 'هرات'],
        ['BAL', 'بلخ'],
        ['KAN', 'کندهار'],
        ['NAN', 'ننگرهار'],
        ['GHA', 'غزنی'],
        ['URU', 'ارزگان'],
        ['BDG', 'بادغیس'],
        ['BAM', 'بامیان'],
        ['BDS', 'بدخشان'],
        ['BGL', 'بغلان'],
        ['PAR', 'پروان'],
        ['PIA', 'پکتیا'],
        ['PKA', 'پکتیکا'],
        ['PAN', 'پنجشیر'],
        ['TAK', 'تخار'],
        ['JOW', 'جوزجان'],
        ['KHO', 'خوست'],
        ['DAY', 'دایکندی'],
        ['ZAB', 'زابل'],
        ['SAR', 'سرپل'],
        ['SAM', 'سمنگان'],
        ['GHO', 'غور'],
        ['FYB', 'فاریاب'],
        ['FRA', 'فراه'],
        ['KAP', 'کاپیسا'],
        ['KDZ', 'کندز'],
        ['KNR', 'کنر'],
        ['LAG', 'لغمان'],
        ['LOG', 'لوگر'],
        ['WAR', 'میدان وردک'],
        ['NUR', 'نورستان'],
        ['NIM', 'نیمروز'],
        ['HEL', 'هلمند'],
    ];

    public function run(): void
    {
        // بی رویداد: داده‌ی اولیه در تاریخچه‌ی فعالیت نوشته نشود
        Province::withoutEvents(function (): void {
            foreach (self::PROVINCES as $index => [$code, $name]) {
                Province::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => ['fa' => $name],
                        'position' => ($index + 1) * 10,
                        'is_active' => true,
                        'delivery_enabled' => in_array($code, self::DELIVERY_FROM_START, true),
                    ],
                );
            }
        });
    }
}
