{{--
    حساب کاربری مشتری.
    موبایل (الگو: پروفایل دیجی‌کالا، ۵ قوس ۱۴۰۵): نام و شماره ← خانه‌های «سفارش‌های من» با شمار ← لیست منو با
    فلش ← فروشندگی ← سه سفارش آخر ← معلومات و رمز.
    کمپیوتر: ستون کنار (نام و منو) و متن اصلی. ترتیب موبایل با order-* (والد flex)، کمپیوتر با grid.
--}}
@extends('storefront.layouts.app')

@section('title', __('customer.account.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php
        $supportLink = $store->whatsappLink() ?? $store->telLink();
        $menu = [
            ['href' => route('account.orders'), 'icon' => 'heroicon-o-shopping-bag', 'label' => __('customer.account.menu.orders')],
            ['href' => route('order.track'), 'icon' => 'heroicon-o-magnifying-glass', 'label' => __('checkout.track.title')],
            ['href' => route('account.addresses'), 'icon' => 'heroicon-o-map-pin', 'label' => __('customer.addresses.title')],
            ['href' => '#profile', 'icon' => 'heroicon-o-user-circle', 'label' => __('customer.account.profile')],
            ['href' => '#password', 'icon' => 'heroicon-o-lock-closed', 'label' => __('customer.account.password')],
            ['href' => $seller ? route('seller.onboarding') : route('seller.landing'), 'icon' => 'heroicon-o-building-storefront', 'label' => __('customer.account.menu.seller')],
        ];
        if ($supportLink) {
            $menu[] = ['href' => $supportLink, 'icon' => 'heroicon-o-chat-bubble-left-right', 'label' => __('store.support.menu'), 'external' => str_starts_with($supportLink, 'http')];
        }
    @endphp

    <div class="container-page py-4 md:py-10">
        <div class="flex flex-col gap-4 md:grid md:grid-cols-[16rem_1fr] md:items-start md:gap-6">
            {{-- ستون کنار (در موبایل بچه‌هایش با order در جای خود می‌نشینند) --}}
            <aside class="contents md:block md:space-y-4">
                {{-- ۱. نام و شماره --}}
                <div class="card order-1 p-5 md:order-none">
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                            @svg('heroicon-o-user', 'size-6')
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold text-gray-900">{{ $customer->name }}</p>
                            <p class="text-sm text-gray-500" dir="ltr">{{ \App\Support\Phone::display($customer->phone) }}</p>
                        </div>
                        <a href="#profile" class="flex size-11 shrink-0 items-center justify-center rounded-full text-sky-700 hover:bg-sky-50"
                           aria-label="{{ __('customer.account.edit_profile') }}">
                            @svg('heroicon-o-pencil', 'size-5')
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        {{ __('customer.account.member_since', ['date' => \App\Support\Jalali::date($customer->created_at)]) }}
                    </p>
                </div>

                {{-- ۳. منو (لیست با فلش، مثل دیجی‌کالا) --}}
                <nav class="card order-3 overflow-hidden md:order-none" aria-label="{{ __('customer.account.title') }}">
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($menu as $item)
                            <li>
                                <a href="{{ $item['href'] }}" @if ($item['external'] ?? false) target="_blank" rel="noopener" @endif
                                   class="flex min-h-14 items-center gap-3 px-4 text-gray-800 hover:bg-gray-50">
                                    @svg($item['icon'], 'size-6 shrink-0 text-gray-500')
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @svg('heroicon-m-chevron-left', 'size-5 text-gray-400 ltr:rotate-180')
                                </a>
                            </li>
                        @endforeach
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-14 w-full items-center gap-3 px-4 text-red-700 hover:bg-red-50">
                                    @svg('heroicon-o-arrow-right-on-rectangle', 'size-6 shrink-0')
                                    <span class="flex-1 text-start">{{ __('customer.account.logout') }}</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <div class="contents md:block md:min-w-0 md:space-y-6">
                <div class="order-first md:order-none">
                    <x-storefront.flash />
                </div>

                <h1 class="sr-only md:not-sr-only md:text-xl md:font-bold md:text-gray-900">{{ __('customer.account.hello', ['name' => $customer->name]) }}</h1>

                {{-- ۲. سفارش‌های من: چهار خانه با شمار --}}
                <section class="card order-2 p-4 md:order-none md:p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="border-b-2 border-red-500 pb-1 font-bold text-gray-900">{{ __('customer.account.menu.orders') }}</h2>
                        <a href="{{ route('account.orders') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-sky-700">
                            {{ __('store.home.see_all') }} @svg('heroicon-m-chevron-left', 'size-4 ltr:rotate-180')
                        </a>
                    </div>
                    <x-storefront.order-tiles :counts="$groupCounts" />
                </section>

                {{-- ۴. فروشندگی --}}
                <div class="order-4 md:order-none">
                    @if ($seller)
                        <a href="{{ route('seller.onboarding') }}" class="card flex items-center gap-4 p-5 hover:shadow-md">
                            @svg('heroicon-o-building-storefront', 'size-8 shrink-0 text-accent-600')
                            <div class="flex-1">
                                <p class="font-bold text-gray-900">{{ $seller->store_name ?: __('customer.account.menu.seller') }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ __('customer.account.seller_status', ['status' => $seller->status->label()]) }}</p>
                            </div>
                            <span class="text-sm font-medium text-brand-700">{{ __('customer.account.seller_open') }}</span>
                        </a>
                    @else
                        <div class="flex flex-col items-start gap-4 rounded-2xl bg-navy-800 p-5 text-white sm:flex-row sm:items-center">
                            @svg('heroicon-o-building-storefront', 'size-8 shrink-0 text-accent-400')
                            <div class="flex-1">
                                <p class="font-bold">{{ __('customer.account.seller_cta_title') }}</p>
                                <p class="mt-1 text-sm text-white/80">{{ __('customer.account.seller_cta_text') }}</p>
                            </div>
                            <a href="{{ route('seller.landing') }}" class="btn btn-accent">{{ __('customer.account.seller_cta_button') }}</a>
                        </div>
                    @endif
                </div>

                {{-- ۵. سه سفارش آخر --}}
                @if ($orders->isNotEmpty())
                    <section class="card order-5 p-5 md:order-none">
                        <h2 class="flex items-center gap-2 font-bold text-gray-900">
                            @svg('heroicon-o-clock', 'size-5 text-gray-400') {{ __('customer.account.recent_orders') }}
                        </h2>
                        <ul class="mt-3 divide-y divide-gray-100 text-sm">
                            @foreach ($orders as $order)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                    <a href="{{ route('order.show', ['uuid' => $order->uuid]) }}" class="font-medium text-brand-700 hover:underline" dir="ltr">{{ $order->number }}</a>
                                    <span class="text-xs text-gray-500">{{ \App\Support\Jalali::date($order->placed_at) }}</span>
                                    <span class="text-gray-600">{{ __('checkout.status.'.$order->status) }}</span>
                                    <span class="font-bold text-gray-900">{{ \App\Support\Money::format($order->total) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- ۶. معلومات من --}}
                <section id="profile" class="card order-6 scroll-mt-28 p-5 md:order-none md:p-6">
                    <h2 class="font-bold text-gray-900">{{ __('customer.account.profile') }}</h2>
                    <form method="post" action="{{ route('account.profile') }}" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate>
                        @csrf
                        @method('put')
                        <x-storefront.field name="name" :label="__('customer.account.name')" :value="$customer->name" required maxlength="100" autocomplete="name" />
                        <div>
                            <span class="form-label">{{ __('customer.account.phone') }}</span>
                            <p class="form-input flex items-center bg-gray-50 text-gray-500" dir="ltr">{{ $customer->phone }}</p>
                            <p class="form-help">{{ __('customer.account.phone_help') }}</p>
                        </div>
                        <x-storefront.field name="email" type="email" :label="__('customer.account.email')" :value="$customer->email" dir="ltr" autocomplete="email" maxlength="150" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary">{{ __('customer.account.save_profile') }}</button>
                        </div>
                    </form>
                </section>

                {{-- ۷. تغییر رمز --}}
                <section id="password" class="card order-7 scroll-mt-28 p-5 md:order-none md:p-6">
                    <h2 class="font-bold text-gray-900">{{ __('customer.account.password') }}</h2>
                    <form method="post" action="{{ route('account.password') }}" class="mt-4 grid gap-4 sm:grid-cols-3" novalidate>
                        @csrf
                        @method('put')
                        <x-storefront.field name="current_password" type="password" :label="__('customer.account.current_password')" required autocomplete="current-password" dir="ltr" />
                        <x-storefront.field name="password" type="password" :label="__('customer.account.new_password')" required autocomplete="new-password" dir="ltr" :help="__('customer.register.password_help')" />
                        <x-storefront.field name="password_confirmation" type="password" :label="__('customer.account.new_password_confirmation')" required autocomplete="new-password" dir="ltr" />
                        <div class="sm:col-span-3">
                            <button type="submit" class="btn btn-outline">{{ __('customer.account.save_password') }}</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
@endsection
