{{--
    صفحه‌ی اصلی — نسخه‌ی مرحله‌ی ۲ (پیش از کاتالوگ): معرفی فروشگاه، دسته‌های اصلی، تحویل، اعتماد.
    در مرحله‌ی ۳ بنرها، دسته‌ها و اجناس واقعی از دیتابیس جای این بخش‌ها را می‌گیرد.
--}}
@extends('storefront.layouts.app')

@section('title', __('store.home.title'))
@section('description', __('store.home.meta_description', ['name' => $store->name()]))

@section('content')
    @php
        $deliveryText = $store->deliveryProvincesText();
        $tileColors = ['bg-emerald-50 text-emerald-800', 'bg-orange-50 text-orange-800', 'bg-sky-50 text-sky-800', 'bg-violet-50 text-violet-800'];
        $trust = [
            ['icon' => 'heroicon-o-banknotes', 'key' => 'cod'],
            ['icon' => 'heroicon-o-credit-card', 'key' => 'online'],
            ['icon' => 'heroicon-o-truck', 'key' => 'delivery'],
            ['icon' => 'heroicon-o-chat-bubble-left-right', 'key' => 'support'],
        ];
    @endphp

    {{-- معرفی --}}
    <section class="bg-gradient-to-b from-brand-50 to-gray-50">
        <div class="container-page py-10 text-center md:py-16">
            @unless ($store->isOpen())
                <span class="inline-block rounded-full bg-accent-100 px-4 py-1 text-sm font-medium text-accent-600">
                    {{ __('store.home.soon_badge') }}
                </span>
            @endunless

            <h1 class="mx-auto mt-4 max-w-2xl text-2xl leading-10 font-bold text-gray-900 md:text-4xl md:leading-[3.5rem]">
                {{ $store->isOpen() ? $store->name() : __('store.home.soon_heading') }}
            </h1>
            <p class="mx-auto mt-4 max-w-xl leading-8 text-gray-600">
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

    {{-- دسته‌های اصلی --}}
    <section class="container-page mt-8">
        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('store.home.categories_heading') }}</h2>
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach (__('store.home.categories') as $category)
                <li class="card p-4">
                    <span class="mb-3 flex size-11 items-center justify-center rounded-xl {{ $tileColors[$loop->index % count($tileColors)] }}">
                        @svg('heroicon-o-shopping-bag', 'size-6')
                    </span>
                    <h3 class="font-bold text-gray-900">{{ $category['title'] }}</h3>
                    <p class="mt-1 text-xs leading-6 text-gray-500">{{ $category['text'] }}</p>
                </li>
            @endforeach
        </ul>
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
