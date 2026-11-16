{{--
    سربرگ فروشگاه: نوار خبر تحویل، لوگو و نام، جستجو، دکمه‌های حساب و سبد.
    بخش‌هایی که هنوز ساخته نشده (جستجو، سبد، حساب) خودکار «به‌زودی» نشان داده می‌شود
    و وقتی آدرس‌شان در مرحله‌های بعد ساخته شد، بی تغییر این فایل فعال می‌شود.
--}}
@props(['store'])
@php
    $deliveryText = $store->deliveryProvincesText();
    $hasSearch = Route::has('search');
    $actions = [
        ['route' => 'account', 'icon' => 'heroicon-o-user', 'label' => __('store.nav.account')],
        ['route' => 'cart.show', 'icon' => 'heroicon-o-shopping-cart', 'label' => __('store.nav.cart')],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-gray-200 bg-white">
    {{-- نوار خبر: تحویل فعلاً در کدام ولایت‌ها --}}
    @if ($deliveryText !== '')
        <div class="bg-brand-700 text-white">
            <p class="container-page flex items-center justify-center gap-2 py-1.5 text-center text-xs sm:text-sm">
                @svg('heroicon-o-truck', 'size-4 shrink-0')
                <span>{{ __('store.header.delivery_notice', ['provinces' => $deliveryText]) }}</span>
            </p>
        </div>
    @endif

    <div class="container-page flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
        {{-- لوگو و نام --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $store->name() }}">
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="" width="40" height="40" class="size-10 rounded-lg object-contain">
            @else
                <span class="flex size-10 items-center justify-center rounded-lg bg-brand-600 text-white">
                    @svg('heroicon-o-building-storefront', 'size-6')
                </span>
            @endif
            <span class="text-base font-bold text-gray-900 sm:text-lg">{{ $store->name() }}</span>
        </a>

        {{-- دکمه‌های حساب و سبد (در موبایل در نوار پایین است) --}}
        <nav class="ms-auto hidden items-center gap-1 md:order-3 md:flex" aria-label="{{ __('store.nav.main') }}">
            @foreach ($actions as $action)
                @if (Route::has($action['route']))
                    <a href="{{ route($action['route']) }}" class="btn btn-outline border-transparent px-3">
                        @svg($action['icon'], 'size-6')
                        <span>{{ $action['label'] }}</span>
                    </a>
                @else
                    <span class="btn cursor-not-allowed px-3 text-gray-400" aria-disabled="true" title="{{ __('store.header.soon') }}">
                        @svg($action['icon'], 'size-6')
                        <span>{{ $action['label'] }}</span>
                    </span>
                @endif
            @endforeach
        </nav>

        {{-- تماس سریع در موبایل --}}
        @if ($store->telLink())
            <a href="{{ $store->telLink() }}" class="btn btn-outline ms-auto px-3 md:hidden" aria-label="{{ __('store.header.call') }}">
                @svg('heroicon-o-phone', 'size-5')
            </a>
        @endif

        {{-- جستجو --}}
        <form action="{{ $hasSearch ? route('search') : '#' }}" method="get" role="search"
              class="order-last w-full md:order-2 md:w-auto md:flex-1">
            <label class="relative block">
                <span class="sr-only">{{ __('store.nav.search') }}</span>
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-gray-400">
                    @svg('heroicon-o-magnifying-glass', 'size-5')
                </span>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ $hasSearch ? __('store.header.search_placeholder') : __('store.header.search_placeholder').' ('.__('store.header.soon').')' }}"
                       @disabled(! $hasSearch)
                       class="h-11 w-full rounded-xl border border-gray-200 bg-gray-100 ps-10 pe-4 text-sm placeholder:text-gray-500 focus:border-brand-600 focus:bg-white disabled:cursor-not-allowed disabled:opacity-70">
            </label>
        </form>
    </div>
</header>
