<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصمیم صاحب پروژه (۲۶ عقرب ۱۴۰۵): کرایه‌ی اولیه کمی گران بود.
 *   - بار زیر ۵ کیلو هیچ‌وقت بیشتر از ۲۰۰ افغانی نشود (۱۰۰ تا ۲۰۰ طبق روز تحویل).
 *   - بار بالاتر از ۵ کیلو بین ۲۵۰ تا ۸۰۰ افغانی.
 *
 * عددها فقط وقتی عوض می‌شود که کابل هنوز «همان عددهای اولیه‌ی seeder» را داشته باشد؛ اگر مدیر خودش
 * چیزی را در پنل عوض کرده، دست نمی‌خورد.
 *
 *            روز: ۱    ۲    ۳    ۴    ۵          وزن: ≤۵kg  ≤۱۰  ≤۲۰  ≤۵۰  بیشتر
 *   قدیم:       ۳۰۰  ۲۵۰  ۲۰۰  ۱۵۰  ۱۰۰                0    ۱۰۰  ۲۰۰  ۴۰۰  ۸۰۰
 *   تازه:       ۲۰۰  ۱۷۵  ۱۵۰  ۱۲۵  ۱۰۰                0    ۱۵۰  ۲۵۰  ۴۰۰  ۶۰۰
 *
 * بازه‌ها: سبک ۱۰۰..۲۰۰ ؛ سنگین‌تر از ۵ کیلو: کمترین ۱۰۰+۱۵۰=۲۵۰ و بیشترین ۲۰۰+۶۰۰=۸۰۰.
 */
return new class extends Migration
{
    private const OLD = ['slots' => [300, 250, 200, 150, 100], 'tiers' => [0, 100, 200, 400, 800]];

    private const NEW = ['slots' => [200, 175, 150, 125, 100], 'tiers' => [0, 150, 250, 400, 600]];

    /** گرام هر پله (بی سقف = null). */
    private const TIER_LIMITS = [5000, 10000, 20000, 50000, null];

    public function up(): void
    {
        $this->replace(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->replace(self::NEW, self::OLD);
    }

    /**
     * @param  array{slots: list<int>, tiers: list<int>}  $from
     * @param  array{slots: list<int>, tiers: list<int>}  $to
     */
    private function replace(array $from, array $to): void
    {
        $zoneId = DB::table('delivery_zones')
            ->join('provinces', 'provinces.id', '=', 'delivery_zones.province_id')
            ->where('provinces.code', 'KAB')
            ->value('delivery_zones.id');
        if ($zoneId === null) {
            return;
        }

        $slots = DB::table('delivery_slots')->where('zone_id', $zoneId)->orderBy('day_rank')->get();
        $tiers = DB::table('delivery_weight_tiers')->where('zone_id', $zoneId)->orderBy('position')->get();

        $slotsMatch = $slots->map(fn ($s) => (int) round((float) $s->fee))->all() === $from['slots'];
        $tiersMatch = $tiers->map(fn ($t) => (int) round((float) $t->extra_fee))->all() === $from['tiers']
            && $tiers->map(fn ($t) => $t->up_to_grams === null ? null : (int) $t->up_to_grams)->all() === self::TIER_LIMITS;

        // مدیر عددها را عوض کرده: دست نزن
        if (! $slotsMatch || ! $tiersMatch) {
            return;
        }

        foreach ($slots as $i => $slot) {
            DB::table('delivery_slots')->where('id', $slot->id)->update(['fee' => $to['slots'][$i]]);
        }
        foreach ($tiers as $i => $tier) {
            DB::table('delivery_weight_tiers')->where('id', $tier->id)->update(['extra_fee' => $to['tiers'][$i]]);
        }
    }
};
