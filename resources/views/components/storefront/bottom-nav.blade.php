{{--
    نوار پایین ثابت در موبایل: خانه، دسته‌ها، جستجو، سبد، حساب.
    هر بخش که آدرسش هنوز ساخته نشده کم‌رنگ و غیرفعال است و در مرحله‌های بعد خودکار فعال می‌شود.
--}}
@php
    $items = [
        ['route' => 'home', 'icon' => 'heroicon-o-home', 'label' => __('store.nav.home')],
        ['route' => 'categories', 'icon' => 'heroicon-o-squares-2x2', 'label' => __('store.nav.categories')],
        ['route' => 'search', 'icon' => 'heroicon-o-magnifying-glass', 'label' => __('store.nav.search')],
        ['route' => 'cart.show', 'icon' => 'heroicon-o-shopping-cart', 'label' => __('store.nav.cart')],
        ['route' => 'account', 'icon' => 'heroicon-o-user', 'label' => __('store.nav.account')],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
     aria-label="{{ __('store.nav.bottom') }}">
    <ul class="grid grid-cols-5">
        @foreach ($items as $item)
            @php $active = Route::has($item['route']) && request()->routeIs($item['route']); @endphp
            <li>
                @if (Route::has($item['route']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                       class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs {{ $active ? 'font-bold text-brand-700' : 'text-gray-600' }}">
                        @svg($item['icon'], 'size-6')
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs text-gray-300"
                          aria-disabled="true" title="{{ __('store.header.soon') }}">
                        @svg($item['icon'], 'size-6')
                        <span>{{ $item['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
