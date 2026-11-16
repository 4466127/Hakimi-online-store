{{--
    سربرگ فروشگاه (الگو: دیجی‌کالا):
      ردیف ۱: لوگو، جستجو، حساب و سبد
      ردیف ۲ (کمپیوتر و تبلت): منوی «دسته‌بندی کالاها»، «در بازارک بفروشید!»، ولایت‌های تحویل
    بخش‌هایی که هنوز ساخته نشده (جستجو، سبد) خودکار «به‌زودی» نشان داده می‌شود و وقتی
    آدرس‌شان در مرحله‌های بعد ساخته شد، بی تغییر این فایل فعال می‌شود.
--}}
@props(['store'])
@php
    $deliveryText = $store->deliveryProvincesText();
    $hasSearch = Route::has('search');
    $hasCart = Route::has('cart.show');
    $customer = auth('customer')->user();
    $menu = app(\App\Services\CategoryMenu::class)->menu();
@endphp

<header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
    {{-- نوار خبر: تحویل فعلاً در کدام ولایت‌ها --}}
    @if ($deliveryText !== '')
        <div class="bg-navy-800 text-white">
            <p class="container-page flex items-center justify-center gap-2 py-1.5 text-center text-xs sm:text-sm">
                @svg('heroicon-o-truck', 'size-4 shrink-0 text-accent-400')
                <span>{{ __('store.header.delivery_notice', ['provinces' => $deliveryText]) }}</span>
            </p>
        </div>
    @endif

    <div class="container-page flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
        {{-- لوگو --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="{{ $store->name() }}">
            <img src="{{ $store->logoUrl() }}" alt="{{ $store->name() }}" width="154" height="44"
                 class="h-10 w-auto sm:h-11" fetchpriority="high">
        </a>

        {{-- حساب و سبد (کمپیوتر) --}}
        <nav class="ms-auto hidden items-center gap-1 md:order-3 md:flex" aria-label="{{ __('store.nav.main') }}">
            @if ($customer)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-outline border-transparent px-3" @click="open = ! open" :aria-expanded="open" aria-haspopup="true">
                        @svg('heroicon-o-user', 'size-6')
                        <span class="max-w-32 truncate">{{ $customer->name }}</span>
                        @svg('heroicon-m-chevron-down', 'size-4 text-gray-400')
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity
                         class="absolute end-0 top-full z-50 mt-1 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('account') }}" class="flex min-h-11 items-center gap-2 px-4 text-sm hover:bg-gray-50">
                            @svg('heroicon-o-user-circle', 'size-5 text-gray-400') {{ __('store.nav.my_account') }}
                        </a>
                        <a href="{{ $customer->seller ? route('seller.onboarding') : route('seller.landing') }}" class="flex min-h-11 items-center gap-2 px-4 text-sm hover:bg-gray-50">
                            @svg('heroicon-o-building-storefront', 'size-5 text-gray-400') {{ __('store.nav.seller_panel') }}
                        </a>
                        <form method="post" action="{{ route('logout') }}" class="border-t border-gray-100">
                            @csrf
                            <button type="submit" class="flex min-h-11 w-full items-center gap-2 px-4 text-sm text-red-700 hover:bg-red-50">
                                @svg('heroicon-o-arrow-right-on-rectangle', 'size-5') {{ __('store.nav.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline px-3">
                    @svg('heroicon-o-arrow-left-on-rectangle', 'size-5')
                    <span>{{ __('store.nav.login') }}</span>
                </a>
            @endif

            <span class="mx-1 h-6 w-px bg-gray-200" aria-hidden="true"></span>

            @if ($hasCart)
                <a href="{{ route('cart.show') }}" class="btn btn-outline border-transparent px-3" aria-label="{{ __('store.nav.cart') }}">
                    @svg('heroicon-o-shopping-cart', 'size-6')
                </a>
            @else
                <span class="btn cursor-not-allowed px-3 text-gray-300" aria-disabled="true" title="{{ __('store.nav.cart') }} — {{ __('store.header.soon') }}">
                    @svg('heroicon-o-shopping-cart', 'size-6')
                    <span class="sr-only">{{ __('store.nav.cart') }} ({{ __('store.header.soon') }})</span>
                </span>
            @endif
        </nav>

        {{-- تماس سریع در موبایل --}}
        @if ($store->telLink())
            <a href="{{ $store->telLink() }}" class="btn btn-outline ms-auto px-3 md:hidden" aria-label="{{ __('store.header.call') }}">
                @svg('heroicon-o-phone', 'size-5')
            </a>
        @endif

        {{-- جستجو --}}
        <form action="{{ $hasSearch ? route('search') : '#' }}" method="get" role="search"
              class="order-last w-full md:order-2 md:w-auto md:max-w-xl md:flex-1">
            <label class="relative block">
                <span class="sr-only">{{ __('store.nav.search') }}</span>
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-gray-400">
                    @svg('heroicon-o-magnifying-glass', 'size-5')
                </span>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ $hasSearch ? __('store.header.search_placeholder') : __('store.header.search_placeholder').' ('.__('store.header.soon').')' }}"
                       @disabled(! $hasSearch)
                       class="h-11 w-full rounded-xl border border-transparent bg-gray-100 ps-10 pe-4 text-sm placeholder:text-gray-500 focus:border-brand-600 focus:bg-white disabled:cursor-not-allowed disabled:opacity-80">
            </label>
        </form>
    </div>

    {{-- ردیف ۲: منوی دسته‌ها و لینک‌ها (فقط کمپیوتر و تبلت؛ در موبایل نوار پایین «دسته‌ها» دارد) --}}
    <div class="container-page hidden items-center gap-1 text-sm md:flex">
        <x-storefront.mega-menu :menu="$menu" />

        <span class="mx-2 h-5 w-px bg-gray-200" aria-hidden="true"></span>

        <a href="{{ route('seller.landing') }}"
           class="inline-flex min-h-11 items-center gap-1.5 border-b-2 px-2 font-medium {{ request()->routeIs('seller.*') ? 'border-accent-500 text-navy-800' : 'border-transparent text-gray-600 hover:text-navy-800' }}">
            @svg('heroicon-o-building-storefront', 'size-5 text-accent-600')
            {{ __('seller.nav_link') }}
        </a>

        @if ($deliveryText !== '')
            <span class="ms-auto inline-flex items-center gap-1.5 text-xs text-gray-500">
                @svg('heroicon-o-map-pin', 'size-4')
                {{ __('store.header.delivery_to', ['provinces' => $deliveryText]) }}
            </span>
        @endif
    </div>
</header>
