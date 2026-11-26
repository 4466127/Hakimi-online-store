{{--
    دو چیز شناور بالای نوار پایین موبایل (الگو: دیجی‌کالا، ۵ قوس ۱۴۰۵):
      - دکمه‌ی گرد پشتیبانی (واتساپ فروشگاه، یا تلفن) — فقط در صفحه‌ی اصلی، دسته‌ها و حساب
        (در صفحه‌ی جنس، سبد و تسویه دکمه‌های خود صفحه مهم‌ترند).
      - پیام کوچک «کد … را در تسویه بزنید» — فقط در صفحه‌ی اصلی و وقتی کد تخفیف سایت هست؛ با «×» بسته می‌شود.
    بی حرکت و بی چشمک.
--}}
@inject('store', 'App\Services\StoreInfo')
@php
    $onHome = request()->routeIs('home');
    $showSupport = request()->routeIs('home', 'categories', 'account', 'account.orders');
    $supportLink = $store->whatsappLink() ?? $store->telLink();
    $promo = $onHome ? \App\Models\DiscountCode::sitePromo() : null;
@endphp

@if ($showSupport && $supportLink)
    <a href="{{ $supportLink }}" @if (str_starts_with($supportLink, 'http')) target="_blank" rel="noopener" @endif
       class="fixed end-3 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-40 flex size-13 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-navy-800 text-white shadow-lg md:hidden"
       aria-label="{{ __('store.support.button') }}">
        @svg('heroicon-o-chat-bubble-left-right', 'size-6')
    </a>
@endif

@if ($promo)
    <div x-data="dismissible(@js('bz-promo-hint-'.$promo->code))" x-show="open" x-cloak
         class="fixed start-3 {{ $showSupport && $supportLink ? 'end-19' : 'end-3' }} bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-30 md:hidden">
        <div class="flex items-center gap-1 rounded-full bg-gray-900/90 py-1 ps-4 pe-1 text-sm text-white shadow-lg">
            <a href="#promo" class="flex min-h-10 min-w-0 flex-1 items-center gap-1">
                <span class="truncate">{!! __('store.promo.hint', ['code' => '<span dir="ltr" class="font-bold">'.e($promo->code).'</span>']) !!}</span>
                @svg('heroicon-m-chevron-left', 'size-4 shrink-0 ltr:rotate-180')
            </a>
            <button type="button" x-on:click="close()" class="flex size-10 shrink-0 items-center justify-center rounded-full text-white/80 hover:bg-white/10"
                    aria-label="{{ __('store.promo.hint_close') }}">
                @svg('heroicon-m-x-mark', 'size-5')
            </button>
        </div>
    </div>
@endif
