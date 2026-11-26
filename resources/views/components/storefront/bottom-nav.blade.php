{{--
    نوار پایین موبایل (الگو: دیجی‌کالا، ۵ قوس ۱۴۰۵): قرص شناور گرد با سایه، بخش فعال در دایره‌ی خاکستری.
    خانه، دسته‌ها، جستجو، سبد، حساب. نام هر بخش کوچک زیر آیکون می‌ماند (برای مشتری‌ای که با آیکون آشنا نیست).
    هر بخش که آدرسش هنوز ساخته نشده کم‌رنگ و غیرفعال است.
--}}
@php
    $cartCount = Route::has('cart.show') ? app(\App\Services\Cart\CartService::class)->count(request()) : 0;
    $items = [
        ['route' => 'home', 'match' => ['home'], 'icon' => 'heroicon-o-home', 'active_icon' => 'heroicon-s-home', 'label' => __('store.nav.home')],
        ['route' => 'categories', 'match' => ['categories', 'category.show'], 'icon' => 'heroicon-o-squares-2x2', 'active_icon' => 'heroicon-s-squares-2x2', 'label' => __('store.nav.categories')],
        ['route' => 'search', 'match' => ['search'], 'icon' => 'heroicon-o-magnifying-glass', 'active_icon' => 'heroicon-s-magnifying-glass', 'label' => __('store.nav.search')],
        ['route' => 'cart.show', 'match' => ['cart.show', 'checkout.show'], 'icon' => 'heroicon-o-shopping-cart', 'active_icon' => 'heroicon-s-shopping-cart', 'label' => __('store.nav.cart')],
        ['route' => 'account', 'match' => ['account', 'account.*', 'login', 'register'], 'icon' => 'heroicon-o-user', 'active_icon' => 'heroicon-s-user', 'label' => __('store.nav.account')],
    ];
@endphp

<nav class="fixed inset-x-3 bottom-[calc(0.5rem+env(safe-area-inset-bottom))] z-40 rounded-full bg-white/95 shadow-[0_4px_24px_rgba(15,23,42,0.18)] ring-1 ring-black/5 backdrop-blur md:hidden"
     aria-label="{{ __('store.nav.bottom') }}">
    <ul class="grid grid-cols-5 px-1.5 py-1.5">
        @foreach ($items as $item)
            @php $active = Route::has($item['route']) && request()->routeIs(...$item['match']); @endphp
            <li>
                @if (Route::has($item['route']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                       class="flex min-h-13 flex-col items-center justify-center gap-0.5 rounded-full text-[0.65rem] leading-4 {{ $active ? 'bg-gray-100 font-bold text-gray-900' : 'text-gray-500' }}">
                        <span class="relative">
                            @svg($active ? $item['active_icon'] : $item['icon'], 'size-6')
                            @if ($item['route'] === 'cart.show' && $cartCount > 0)
                                <span class="absolute -top-1.5 -end-2 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white ring-2 ring-white" aria-hidden="true">{{ \App\Support\Num::toFa((string) min($cartCount, 99)) }}</span>
                                <span class="sr-only">{{ __('cart.cart_count', ['count' => \App\Support\Num::toFa((string) $cartCount)]) }}</span>
                            @endif
                        </span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="flex min-h-13 flex-col items-center justify-center gap-0.5 text-[0.65rem] text-gray-300"
                          aria-disabled="true" title="{{ __('store.header.soon') }}">
                        @svg($item['icon'], 'size-6')
                        <span>{{ $item['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
