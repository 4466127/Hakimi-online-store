<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\DeliveryWeightTier;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * کرایه‌ی تحویل = کرایه‌ی «روز تحویل» + اضافه‌ی «وزن بار» (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵).
 *
 * مثل دیجی‌کالا مشتری از چند روز پیش‌رو یکی را انتخاب می‌کند؛ زودترین روز گران‌تر و هر چه دیرتر ارزان‌تر.
 * سنگین‌تر هم کرایه‌ی بیشتر. همه‌ی عددها (کرایه‌ی هر روز، پله‌های وزنی، ساعت قطع سفارش، روزهای بی تحویل)
 * از پنل («کرایه‌ی تحویل») می‌آید؛ هیچ عددی در این کلاس نیست.
 *
 * ساختن سفارش (مرحله‌ی ۴): options() را به مشتری نشان بده؛ بعد از انتخاب، option() دوباره حساب می‌کند
 * (به تاریخ و کرایه‌ی سمت مرورگر اعتماد نمی‌شود) و جمع در سفارش کاپی می‌شود.
 */
class DeliveryCalculator
{
    /** روش‌های تحویل: درِ خانه (کرایه‌ی روز + وزن) یا حضوری از دفتر مرکزی (بی کرایه). */
    public const METHOD_DELIVERY = 'delivery';

    public const METHOD_PICKUP = 'pickup';

    /** تا چند روز جلو دنبال روز کاری می‌گردیم (جلوی حلقه‌ی بی‌پایان وقتی همه‌ی روزها بی تحویل است). */
    private const SCAN_DAYS = 60;

    /**
     * تحویل حضوری هیچ کرایه‌ای ندارد (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵)؛ نه وزن و نه آدرس مشتری.
     * ولی **روز** می‌خواهد (خواست صاحب پروژه، ۲ قوس ۱۴۰۵): مشتری انتخاب می‌کند کدام روز به
     * نمایندگی برود تا جنس تا آن روز آماده باشد (`pickupOptions`). فعال بودنش با
     * StoreInfo::pickupEnabled() است.
     */
    public function pickupFee(): string
    {
        return Money::of(0);
    }

    /**
     * روزهایی که مشتری می‌تواند جنس را حضوری از نمایندگی بگیرد: مثل روزهای تحویل، ولی **بی کرایه**.
     * زودترین روز فردا است (اگر از ساعت قطع نگذشته)، وگرنه پس‌فردا؛ روزهای بی کار رد می‌شود.
     * ساعت قطع، روزهای بی کار و تعداد روزها از منطقه‌ی تحویل فعال می‌آید؛ اگر منطقه‌ای نبود،
     * از `config('store.pickup')`.
     *
     * @return list<DeliveryOption>
     */
    public function pickupOptions(?CarbonInterface $now = null): array
    {
        $zone = DeliveryZone::query()->active()->with('slots')->first();
        $slots = $zone?->slots->where('is_active', true)->count() ?? 0;

        $cutoff = (int) ($zone->cutoff_hour ?? config('store.pickup.cutoff_hour', 16));
        $daysOff = $zone === null ? (array) config('store.pickup.days_off', [5]) : (array) $zone->days_off;
        $count = $slots > 0 ? $slots : (int) config('store.pickup.days', 5);

        $zero = Money::of(0);
        $tomorrow = $this->today($now)->addDay();

        $options = [];
        foreach ($this->dates($cutoff, $daysOff, $count, $now) as $i => $date) {
            $options[] = new DeliveryOption(
                index: $i + 1,
                date: $date,
                slotFee: $zero,
                weightFee: $zero,
                total: $zero,
                isTomorrow: $date->isSameDay($tomorrow),
            );
        }

        return $options;
    }

    /** گزینه‌ی روزِ انتخاب‌شده برای تحویل حضوری (تاریخ Y-m-d)؛ null اگر آن روز در لیست نیست. */
    public function pickupOption(string $date, ?CarbonInterface $now = null): ?DeliveryOption
    {
        foreach ($this->pickupOptions($now) as $option) {
            if ($option->dateString() === $date) {
                return $option;
            }
        }

        return null;
    }

    /**
     * منطقه‌ی تحویل یک ولایت: منطقه‌ی خودش، وگرنه منطقه‌ی «هر ولایت» (province_id خالی).
     * ولایتی که «تحویل فعال» نیست منطقه ندارد (null).
     */
    public function zoneFor(?Province $province): ?DeliveryZone
    {
        if ($province === null || ! $province->is_active || ! $province->delivery_enabled) {
            return null;
        }

        return DeliveryZone::query()->active()
            ->where(fn ($q) => $q->where('province_id', $province->id)->orWhereNull('province_id'))
            // منطقه‌ی خود ولایت بر منطقه‌ی همگانی مقدم است
            ->orderByRaw('province_id is null')
            ->with(['slots', 'weightTiers'])
            ->first();
    }

    /**
     * وزن کل (گرام) از ردیف‌های سبد. جنس بی وزن، وزن پیش‌فرض منطقه را می‌گیرد.
     *
     * @param  iterable<array{variant: ProductVariant, qty: int|float|string}>  $lines
     */
    public function totalWeight(DeliveryZone $zone, iterable $lines): int
    {
        $total = '0';
        foreach ($lines as $line) {
            $grams = $line['variant']->weight_grams ?? $zone->default_item_weight_grams;
            $total = bcadd($total, bcmul((string) $grams, (string) $line['qty'], 3), 3);
        }

        // به بالا گرد می‌شود تا وزن کمتر از واقعی حساب نشود
        return (int) ceil((float) $total);
    }

    /** سنگین‌تر از سقف منطقه است؟ (خالی = بی سقف) */
    public function isTooHeavy(DeliveryZone $zone, int $grams): bool
    {
        return $zone->max_weight_grams !== null && $grams > $zone->max_weight_grams;
    }

    /**
     * اضافه‌ی کرایه برای این وزن: اولین پله‌ای که وزن در آن می‌گنجد؛ اگر از همه‌ی پله‌ها سنگین‌تر بود،
     * پله‌ی بی سقف، و اگر آن هم نبود بالاترین پله. بی پله = بی اضافه.
     */
    public function weightFee(DeliveryZone $zone, int $grams): string
    {
        $tiers = $zone->weightTiers;
        if ($tiers->isEmpty()) {
            return Money::of(0);
        }

        $bounded = $tiers->whereNotNull('up_to_grams')->sortBy('up_to_grams')->values();
        $open = $tiers->whereNull('up_to_grams')->sortByDesc(fn (DeliveryWeightTier $t) => (float) $t->extra_fee)->first();

        foreach ($bounded as $tier) {
            if ($grams <= $tier->up_to_grams) {
                return Money::of($tier->extra_fee);
            }
        }

        return Money::of(($open ?? $bounded->last())->extra_fee);
    }

    /**
     * روزهای تحویل با کرایه‌ی هر کدام، به ترتیب (ارزان‌ترین آخر).
     * خالی اگر منطقه روز تحویل ندارد، همه‌ی هفته بی تحویل است، یا بار از سقف سنگین‌تر است.
     *
     * @return list<DeliveryOption>
     */
    public function options(DeliveryZone $zone, int $grams, ?CarbonInterface $now = null): array
    {
        if ($this->isTooHeavy($zone, $grams)) {
            return [];
        }

        $slots = $zone->slots->where('is_active', true)->sortBy('day_rank')->values();
        if ($slots->isEmpty()) {
            return [];
        }

        $dates = $this->deliveryDates($zone, $slots->count(), $now);
        $weightFee = $this->weightFee($zone, $grams);
        $tomorrow = $this->today($now)->addDay();

        $options = [];
        foreach ($dates as $i => $date) {
            $slotFee = Money::of($slots[$i]->fee);
            $options[] = new DeliveryOption(
                index: $i + 1,
                date: $date,
                slotFee: $slotFee,
                weightFee: $weightFee,
                total: Money::add($slotFee, $weightFee),
                isTomorrow: $date->isSameDay($tomorrow),
            );
        }

        return $options;
    }

    /** گزینه‌ی روزِ انتخاب‌شده (تاریخ Y-m-d)؛ null اگر آن روز دیگر در لیست نیست. */
    public function option(DeliveryZone $zone, int $grams, string $date, ?CarbonInterface $now = null): ?DeliveryOption
    {
        foreach ($this->options($zone, $grams, $now) as $option) {
            if ($option->dateString() === $date) {
                return $option;
            }
        }

        return null;
    }

    /**
     * کمترین و بیشترین کرایه‌ی ممکن برای بار سبک (برای متن «کرایه از … تا …» در سایت).
     *
     * @return array{min: string, max: string}|null null اگر منطقه روز تحویل ندارد
     */
    public function feeRange(DeliveryZone $zone): ?array
    {
        $fees = $zone->slots->where('is_active', true)->pluck('fee');
        if ($fees->isEmpty()) {
            return null;
        }
        $base = $this->weightFee($zone, 0);

        return [
            'min' => Money::add($fees->sortBy(fn ($f) => (float) $f)->first(), $base),
            'max' => Money::add($fees->sortByDesc(fn ($f) => (float) $f)->first(), $base),
        ];
    }

    /**
     * تاریخ‌های روز کاری پیش‌رو. زودترین: فردا (اگر ساعت از ساعت قطع نگذشته)، وگرنه پس‌فردا؛
     * روزهای بی تحویل (مثلاً جمعه) رد می‌شود.
     *
     * @return list<CarbonImmutable>
     */
    private function deliveryDates(DeliveryZone $zone, int $count, ?CarbonInterface $now): array
    {
        return $this->dates((int) $zone->cutoff_hour, (array) $zone->days_off, $count, $now);
    }

    /**
     * روزهای کاری پیش‌رو با ساعت قطع و روزهای بی کار دلخواه.
     *
     * @param  array<int|string>  $daysOff
     * @return list<CarbonImmutable>
     */
    private function dates(int $cutoffHour, array $daysOff, int $count, ?CarbonInterface $now): array
    {
        $now = $this->now($now);
        $cursor = $now->startOfDay()->addDays($now->hour >= $cutoffHour ? 2 : 1);
        $off = array_map('intval', $daysOff);

        $dates = [];
        for ($i = 0; $i < self::SCAN_DAYS && count($dates) < max(1, $count); $i++, $cursor = $cursor->addDay()) {
            if (! in_array($cursor->dayOfWeekIso, $off, true)) {
                $dates[] = $cursor;
            }
        }

        return $dates;
    }

    private function now(?CarbonInterface $now): CarbonImmutable
    {
        $zone = (string) config('store.timezone', 'Asia/Kabul');

        return ($now === null ? CarbonImmutable::now() : CarbonImmutable::instance($now))->setTimezone($zone);
    }

    private function today(?CarbonInterface $now): CarbonImmutable
    {
        return $this->now($now)->startOfDay();
    }
}
