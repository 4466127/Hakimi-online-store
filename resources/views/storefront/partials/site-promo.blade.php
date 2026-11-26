{{--
    کارت «کد تخفیف در سایت» (خواست صاحب پروژه، ۵ قوس ۱۴۰۵؛ الگو: جاکدی دیجی‌کالا، بی بازی و قرعه).
    کد با دکمه‌ی کاپی؛ اگر کد تاریخ پایان دارد (تا ۳۰ روز)، شمارش معکوس. زمان باقی‌مانده را سرور می‌دهد
    (ساعت موبایل مشتری ممکن است غلط باشد). با «حرکت کمتر» ثانیه‌ها نشان داده نمی‌شود (هر دقیقه به‌روز).
    $promo: DiscountCode
--}}
@php
    use App\Support\Money;
    use App\Support\Num;

    $remaining = $promo->ends_at ? max(0, now()->diffInSeconds($promo->ends_at, false)) : null;
    $showCountdown = $remaining !== null && $remaining <= 30 * 86400;
    $amountText = $promo->isPercent()
        ? __('store.promo.percent', ['percent' => Num::toFa(rtrim(rtrim((string) $promo->value, '0'), '.'))])
        : __('store.promo.fixed', ['amount' => Money::format($promo->value)]);
@endphp

<section id="promo" class="container-page mt-4 md:mt-6" aria-labelledby="promo-title"
         x-data="sitePromo({ code: @js($promo->code), remaining: @js($showCountdown ? (int) $remaining : null) })">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-l from-accent-500 to-red-600 p-4 text-white shadow-sm md:p-6">
        <div class="pointer-events-none absolute -start-8 -top-10 size-36 rounded-full bg-white/10" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -end-6 -bottom-12 size-32 rounded-full bg-white/10" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex size-12 shrink-0 -rotate-12 items-center justify-center rounded-xl bg-white text-red-600 shadow">
                    @svg('heroicon-s-receipt-percent', 'size-7')
                </span>
                <div>
                    <h2 id="promo-title" class="text-base leading-7 font-bold md:text-lg">{{ $promo->tr('promo_title') ?: __('store.promo.default_title') }}</h2>
                    <p class="text-sm text-white/90">
                        {{ $amountText }}
                        @if ($promo->min_order_total)
                            • {{ __('store.promo.min_order', ['amount' => Money::format($promo->min_order_total)]) }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($showCountdown)
                {{-- شمارش معکوس: روز، ساعت، دقیقه، ثانیه (عدد دری، از راست به چپ) --}}
                <div class="flex items-center gap-2" x-show="left > 0" role="timer" aria-live="off"
                     :aria-label="label()">
                    <span class="text-xs text-white/90">{{ __('store.promo.ends_in') }}</span>
                    <div class="flex items-center gap-1" dir="ltr">
                        <template x-if="parts().d > 0">
                            <span class="flex items-center gap-1">
                                <span class="min-w-9 rounded-lg bg-white/95 px-1.5 py-1 text-center text-sm font-bold text-red-700" x-text="fa(parts().d)"></span>
                                <span class="text-xs">{{ __('store.promo.day_short') }}</span>
                            </span>
                        </template>
                        <span class="min-w-9 rounded-lg bg-white/95 px-1.5 py-1 text-center text-sm font-bold text-red-700" x-text="fa(pad(parts().h))"></span>
                        <span class="font-bold">:</span>
                        <span class="min-w-9 rounded-lg bg-white/95 px-1.5 py-1 text-center text-sm font-bold text-red-700" x-text="fa(pad(parts().m))"></span>
                        <template x-if="! reduced">
                            <span class="flex items-center gap-1">
                                <span class="font-bold">:</span>
                                <span class="min-w-9 rounded-lg bg-white/95 px-1.5 py-1 text-center text-sm font-bold text-red-700" x-text="fa(pad(parts().s))"></span>
                            </span>
                        </template>
                    </div>
                </div>
            @endif
        </div>

        {{-- کد + کاپی (مثل کارت «برنده شدی» دیجی‌کالا) --}}
        <div class="relative mt-4 flex items-center gap-2 rounded-xl bg-white p-1.5 ps-4 text-gray-900" x-show="left === null || left > 0">
            <span class="sr-only">{{ __('store.promo.code_is') }}</span>
            <span class="min-w-0 flex-1 truncate text-lg font-bold tracking-widest" dir="ltr" x-ref="code">{{ $promo->code }}</span>
            <button type="button" x-on:click="copy()"
                    class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg bg-red-600 px-4 text-sm font-bold text-white hover:bg-red-700">
                <span x-show="! copied" class="inline-flex items-center gap-1.5">@svg('heroicon-o-document-duplicate', 'size-5') {{ __('store.promo.copy') }}</span>
                <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5">@svg('heroicon-o-check', 'size-5') {{ __('store.promo.copied') }}</span>
            </button>
        </div>
        <p class="relative mt-2 text-xs text-white/90" x-show="left === null || left > 0">{{ __('store.promo.how_to') }}</p>
        <p class="relative mt-3 rounded-xl bg-white/15 px-3 py-2 text-sm" x-show="left === 0" x-cloak>{{ __('store.promo.finished') }}</p>
        <p class="sr-only" aria-live="polite" x-text="copied ? @js(__('store.promo.copied')) : ''"></p>
    </div>
</section>
