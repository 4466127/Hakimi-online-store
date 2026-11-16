<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * منطقه‌ی تحویل «کابل» با کرایه‌ی شروع. قید صاحب پروژه (۲۶ عقرب ۱۴۰۵):
 *
 *   - بار زیر ۵ کیلو: کرایه‌ی کل هیچ‌وقت بیشتر از ۲۰۰ افغانی (۱۰۰ تا ۲۰۰ طبق روز تحویل).
 *   - بار بالاتر از ۵ کیلو: کرایه‌ی کل بین ۲۵۰ تا ۸۰۰ افغانی.
 *
 *   روز تحویل: زودترین روز ۲۰۰؛ هر روز دیرتر ارزان‌تر تا ۱۰۰.
 *   وزن:       تا ۵ کیلو بی اضافه؛ سنگین‌تر، اضافه‌ی بیشتر (کمترین کل: ۱۰۰+۱۵۰=۲۵۰؛ بیشترین: ۲۰۰+۶۰۰=۸۰۰).
 *
 * خود عددها را من چیدم تا در این بازه‌ها بمانند؛ از پنل («کرایه‌ی تحویل») عوض می‌شود.
 * چند بار اجرا کردنش بی‌خطر است: اگر ولایت منطقه دارد، هیچ‌چیز عوض نمی‌شود.
 */
class DeliveryZoneSeeder extends Seeder
{
    /** کرایه‌ی روز اول تا پنجم (افغانی). */
    private const SLOT_FEES = [200, 175, 150, 125, 100];

    /** [تا چند گرام (خالی = بیشتر از همه)، اضافه‌ی کرایه (افغانی)] */
    private const WEIGHT_TIERS = [
        [5000, 0],
        [10000, 150],
        [20000, 250],
        [50000, 400],
        [null, 600],
    ];

    public function run(): void
    {
        $kabul = Province::query()->where('code', 'KAB')->first();
        if ($kabul === null || DeliveryZone::query()->where('province_id', $kabul->id)->exists()) {
            return;
        }

        // بی رویداد: داده‌ی اولیه در تاریخچه‌ی فعالیت نوشته نشود
        DeliveryZone::withoutEvents(function () use ($kabul): void {
            $zone = DeliveryZone::query()->create([
                'name' => ['fa' => 'کابل'],
                'province_id' => $kabul->id,
                'is_active' => true,
                'position' => 0,
                'cutoff_hour' => 16,
                'days_off' => [5],                  // جمعه
                'default_item_weight_grams' => 500,
                'max_weight_grams' => null,
            ]);

            foreach (self::SLOT_FEES as $i => $fee) {
                $zone->slots()->create(['day_rank' => $i + 1, 'fee' => $fee, 'is_active' => true]);
            }
            foreach (self::WEIGHT_TIERS as $i => [$upTo, $fee]) {
                $zone->weightTiers()->create(['up_to_grams' => $upTo, 'extra_fee' => $fee, 'position' => $i]);
            }
        });
    }
}
