{{--
    صفحه‌ی اصلی: معرفی فروشگاه، دسته‌های اصلی، پیشنهاد ویژه و تازه‌ترین اجناس، تحویل، اعتماد.
    بنرها (مرحله‌ی ۹-۴): حداکثر ۵. به خواست صاحب پروژه (۲ قوس ۱۴۰۵) خودکار و حلقوی عوض می‌شوند
    (فاصله: config('store.home.banner_interval_ms')). وقتی مشتری دست می‌زند، موس رویش است، صفحه پنهان است
    یا در تنظیم دستگاه «حرکت کمتر» را خواسته، نمی‌چرخد. ورق زدن دستی هم کار می‌کند.
--}}
@extends('storefront.layouts.app')

@section('title', __('store.home.title'))
@section('description', __('store.home.meta_description', ['name' => $store->name()]))

@section('content')
    @php
        $deliveryText = $store->deliveryProvincesText();
        $tileColors = ['bg-brand-50 text-brand-800', 'bg-accent-50 text-accent-800', 'bg-sky-50 text-sky-800', 'bg-violet-50 text-violet-800'];
        $trust = [
            ['icon' => 'heroicon-o-banknotes', 'key' => 'cod'],
            ['icon' => 'heroicon-o-credit-card', 'key' => 'online'],
            ['icon' => 'heroicon-o-truck', 'key' => 'delivery'],
            ['icon' => 'heroicon-o-chat-bubble-left-right', 'key' => 'support'],
        ];
    @endphp

    {{-- بنرها: تمام‌عرض در کمپیوتر، گرد با حاشیه در موبایل؛ چرخش خودکار حلقوی، ورق زدن دستی،
         دو دکمه‌ی گرد در گوشه‌ی پایین و نقطه‌های نشان‌دهنده‌ی بنر فعلی (قابل زدن) --}}
    @if ($banners->isNotEmpty())
        <section class="mt-3 px-4 md:mt-0 md:px-0" aria-label="{{ __('store.home.banners_label') }}"
                 x-data="{
                     count: {{ $banners->count() }},
                     interval: {{ (int) config('store.home.banner_interval_ms', 1500) }},
                     current: 0,
                     paused: false,
                     timer: null,
                     dir() { return getComputedStyle(this.$refs.track).direction === 'rtl' ? -1 : 1; },
                     index() { const t = this.$refs.track; return Math.round(Math.abs(t.scrollLeft) / Math.max(1, t.clientWidth)); },
                     goTo(i) {
                         const t = this.$refs.track;
                         this.current = (i + this.count) % this.count;
                         t.scrollTo({ left: this.current * this.dir() * t.clientWidth, behavior: 'smooth' });
                     },
                     go(step) { this.goTo(this.index() + step); },
                     start() {
                         if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                         this.timer = setInterval(() => { if (! this.paused && ! document.hidden) { this.go(1); } }, this.interval);
                     },
                 }"
                 x-init="start()"
                 x-on:mouseenter="paused = true" x-on:mouseleave="paused = false"
                 x-on:touchstart.passive="paused = true" x-on:touchend.passive="setTimeout(() => paused = false, 3000)"
                 x-on:focusin="paused = true" x-on:focusout="paused = false">
            <div class="relative overflow-hidden rounded-xl md:rounded-none">
                <div x-ref="track" x-on:scroll.debounce.120ms="current = index()"
                     class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="0">
                    @foreach ($banners as $banner)
                        @php
                            $alt = $banner->tr('title') ?: __('store.home.banner_alt', ['n' => \App\Support\Num::toFa((string) $loop->iteration)]);
                        @endphp
                        <div class="w-full shrink-0 snap-center" aria-roledescription="slide"
                             aria-label="{{ \App\Support\Num::toFa((string) $loop->iteration) }} / {{ \App\Support\Num::toFa((string) $banners->count()) }}">
                            @if ($banner->link_url)
                                <a href="{{ $banner->link_url }}" @if ($banner->isExternal()) rel="noopener" @endif class="block">
                            @endif
                                {{-- کمپیوتر: ارتفاع ثابت و نرم (۲۸۰ تا ۴۸۰ پیکسل)؛ وسط عکس همیشه دیده می‌شود --}}
                                <img src="{{ $banner->imageUrl() }}" alt="{{ $alt }}" width="1600" height="600"
                                     @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                                     class="aspect-[2/1] w-full bg-gray-100 object-cover md:aspect-auto md:h-[clamp(280px,30vw,480px)]">
                            @if ($banner->link_url)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($banners->count() > 1)
                    {{-- نقطه‌ها: بنر فعلی کشیده‌تر --}}
                    <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1 md:bottom-4">
                        @foreach ($banners as $banner)
                            <button type="button" x-on:click="goTo({{ $loop->index }})"
                                    aria-label="{{ __('store.home.banner_go', ['n' => \App\Support\Num::toFa((string) $loop->iteration)]) }}"
                                    x-bind:aria-current="current === {{ $loop->index }} ? 'true' : 'false'"
                                    class="flex h-5 items-center px-0.5">
                                <span class="block h-1.5 rounded-full bg-white shadow transition-[width]"
                                      x-bind:class="current === {{ $loop->index }} ? 'w-5 opacity-100' : 'w-1.5 opacity-60'"></span>
                            </button>
                        @endforeach
                    </div>

                    {{-- دو دکمه‌ی گرد در گوشه‌ی پایین (فقط کمپیوتر؛ در موبایل با انگشت) --}}
                    <div class="absolute end-4 bottom-4 hidden gap-2 md:flex lg:end-8">
                        <button type="button" x-on:click="go(-1)" aria-label="{{ __('store.home.banner_prev') }}"
                                class="flex size-11 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-50">
                            @svg('heroicon-o-arrow-right', 'size-5 ltr:rotate-180')
                        </button>
                        <button type="button" x-on:click="go(1)" aria-label="{{ __('store.home.banner_next') }}"
                                class="flex size-11 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-50">
                            @svg('heroicon-o-arrow-left', 'size-5 ltr:rotate-180')
                        </button>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- کد تخفیف سایت (۵ قوس ۱۴۰۵): کاپی و شمارش معکوس --}}
    @if ($promo ?? null)
        @include('storefront.partials.site-promo', ['promo' => $promo])
    @endif

    {{-- معرفی --}}
    <section class="bg-gradient-to-b from-brand-50 to-gray-50">
        <div class="container-page py-6 text-center md:py-16">
            @unless ($store->isOpen())
                <span class="inline-block rounded-full bg-accent-100 px-4 py-1 text-sm font-medium text-accent-600">
                    {{ __('store.home.soon_badge') }}
                </span>
            @endunless

            <h1 class="mx-auto mt-3 max-w-2xl text-xl leading-9 font-bold text-gray-900 md:mt-4 md:text-4xl md:leading-[3.5rem]">
                {{ $store->isOpen() ? $store->name() : __('store.home.soon_heading') }}
            </h1>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-gray-600 md:mt-4 md:text-base md:leading-8">
                {{ __('store.home.soon_text') }}
            </p>

            @if ($store->hasContact())
                <p class="mt-6 text-sm text-gray-600">{{ __('store.home.soon_contact') }}</p>
                <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                    @if ($store->whatsappLink())
                        <a href="{{ $store->whatsappLink() }}" target="_blank" rel="noopener" class="btn btn-whatsapp">
                            @svg('heroicon-o-chat-bubble-left-right', 'size-5')
                            <span>{{ __('store.header.whatsapp') }}</span>
                        </a>
                    @endif
                    @if ($store->telLink())
                        <a href="{{ $store->telLink() }}" class="btn btn-outline">
                            @svg('heroicon-o-phone', 'size-5')
                            <span dir="ltr">{{ $store->phoneDisplay() }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- دسته‌های اصلی (از پنل مدیریت) --}}
    @if (count($categories) > 0)
        <section class="container-page mt-6 md:mt-8">
            <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('store.home.categories_heading') }}</h2>

            {{-- موبایل: دایره‌های کوچک مثل دیجی‌کالا (ساده، بی حرکت؛ همه دیده می‌شود، بی اسکرول افقی) --}}
            <ul class="grid grid-cols-4 gap-x-2 gap-y-4 md:hidden">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('category.show', ['slug' => $category['slug']]) }}" class="flex flex-col items-center gap-1.5 text-center">
                            <span class="flex size-14 items-center justify-center rounded-full {{ $tileColors[$loop->index % count($tileColors)] }}">
                                @svg($category['icon'] ?? 'heroicon-o-shopping-bag', 'size-7')
                            </span>
                            <span class="line-clamp-2 text-[0.7rem] leading-5 font-medium text-gray-700">{{ $category['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- کمپیوتر و تبلت: کارت با نام زیرگروه‌ها --}}
            <ul class="hidden grid-cols-4 gap-3 md:grid">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('category.show', ['slug' => $category['slug']]) }}" class="card block h-full p-4 transition-shadow hover:shadow-md">
                            <span class="mb-3 flex size-11 items-center justify-center rounded-xl {{ $tileColors[$loop->index % count($tileColors)] }}">
                                @svg($category['icon'] ?? 'heroicon-o-shopping-bag', 'size-6')
                            </span>
                            <h3 class="font-bold text-gray-900">{{ $category['name'] }}</h3>
                            @if (count($category['children']) > 0)
                                <p class="mt-1 line-clamp-2 text-xs leading-6 text-gray-500">
                                    {{ implode('، ', array_column($category['children'], 'name')) }}
                                </p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ویدیوهای اجناس (۵ قوس ۱۴۰۵؛ حداکثر ۳) --}}
    @foreach ($videos ?? [] as $video)
        @include('storefront.partials.home-video', ['video' => $video])
    @endforeach

    {{-- پیشنهاد ویژه: نوار رنگی با ردیف افقی در موبایل (الگو: «پیشنهاد شگفت‌انگیز» دیجی‌کالا، بی شمارش معکوس) --}}
    @if ($featured->isNotEmpty())
        <section class="mt-8 md:mx-auto md:max-w-6xl md:px-4" aria-labelledby="featured-heading">
            <div class="bg-navy-800 py-4 md:rounded-2xl md:px-4 md:py-5">
            <div class="flex items-center justify-between gap-3 px-4 md:px-0">
                <h2 id="featured-heading" class="flex items-center gap-2 text-lg font-bold text-white">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-accent-500 text-white">@svg('heroicon-s-receipt-percent', 'size-5')</span>
                    {{ __('store.home.featured_heading') }}
                </h2>
                <a href="{{ route('search') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-white">
                    {{ __('store.home.see_all') }} @svg('heroicon-m-chevron-left', 'size-4 ltr:rotate-180')
                </a>
            </div>
            {{-- ردیف افقی در همه‌ی اندازه‌ها (با انگشت یا اسکرول)؛ هیچ کارتی تنها در ردیف دوم نمی‌ماند --}}
            <ul class="mt-3 flex snap-x gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:px-0 [&::-webkit-scrollbar]:hidden">
                @foreach ($featured as $product)
                    <li class="w-40 shrink-0 snap-start md:w-52"><x-storefront.product-card :product="$product" /></li>
                @endforeach
            </ul>
            </div>
        </section>
    @endif

    {{-- تازه‌ترین اجناس (فقط وقتی جنس فعال هست) --}}
    @foreach ([['items' => $latest, 'heading' => 'store.home.latest_heading']] as $row)
        @if ($row['items']->isNotEmpty())
            <section class="container-page mt-8">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-900">{{ __($row['heading']) }}</h2>
                    <a href="{{ route('search') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-brand-700">
                        {{ __('store.home.see_all') }} @svg('heroicon-m-chevron-left', 'size-4')
                    </a>
                </div>
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($row['items'] as $product)
                        <li><x-storefront.product-card :product="$product" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    {{-- در بازارک بفروشید --}}
    <section class="container-page mt-8">
        <div class="flex flex-col items-start gap-4 overflow-hidden rounded-2xl bg-navy-800 p-6 text-white md:flex-row md:items-center md:p-8">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10">
                @svg('heroicon-o-building-storefront', 'size-8 text-accent-400')
            </span>
            <div class="flex-1">
                <h2 class="text-lg font-bold md:text-xl">{{ __('store.home.seller_heading') }}</h2>
                <p class="mt-1 text-sm leading-7 text-white/80">{{ __('store.home.seller_text') }}</p>
            </div>
            <a href="{{ route('seller.landing') }}" class="btn btn-accent shrink-0">
                {{ __('store.home.seller_button') }}
                @svg('heroicon-m-chevron-left', 'size-4')
            </a>
        </div>
    </section>

    {{-- تحویل: کدام ولایت‌ها فعال است --}}
    @if ($deliveryText !== '')
        <section class="container-page mt-8">
            <div class="card p-5 md:p-6">
                <h2 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                    @svg('heroicon-o-map-pin', 'size-6 text-brand-600')
                    {{ __('store.home.delivery_heading') }}
                </h2>
                <p class="mt-3 leading-8 text-gray-600">
                    {{ __('store.home.delivery_text', ['provinces' => $deliveryText]) }}
                </p>
                <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                    @foreach ($store->deliveryProvinces() as $province)
                        <li class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 font-medium text-brand-800">
                            @svg('heroicon-o-check-circle', 'size-4')
                            <span>{{ $province }} — {{ __('store.home.delivery_active') }}</span>
                        </li>
                    @endforeach
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-gray-600">
                        @svg('heroicon-o-clock', 'size-4')
                        <span>{{ __('store.home.delivery_other_provinces') }} — {{ __('store.home.delivery_soon') }}</span>
                    </li>
                </ul>
            </div>
        </section>
    @endif

    {{-- چرا از ما بخرید --}}
    <section class="container-page mt-8">
        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('store.trust.heading') }}</h2>
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($trust as $item)
                <li class="card p-4">
                    @svg($item['icon'], 'size-7 text-brand-600')
                    <h3 class="mt-2 text-sm font-bold text-gray-900">{{ __('store.trust.'.$item['key'].'.title') }}</h3>
                    <p class="mt-1 text-xs leading-6 text-gray-500">
                        {{ __('store.trust.'.$item['key'].'.text', ['provinces' => $deliveryText]) }}
                    </p>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
