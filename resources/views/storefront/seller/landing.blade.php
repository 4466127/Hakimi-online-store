{{-- معرفی «در بازارک بفروشید» (الگو: «در دیجی‌کالا بفروشید»). --}}
@extends('storefront.layouts.app')

@section('title', __('seller.landing.title'))
@section('description', __('seller.landing.meta_description'))

@section('content')
    @php
        $deliveryText = $store->deliveryProvincesText();
        $ctaUrl = route('seller.onboarding');
        $ctaLabel = match (true) {
            $seller === null => __('seller.landing.cta'),
            $seller->status->value === 'draft' => __('seller.landing.cta_continue'),
            default => __('seller.landing.cta_status'),
        };
    @endphp

    {{-- معرفی --}}
    <section class="bg-navy-800 text-white">
        <div class="container-page grid items-center gap-8 py-10 md:grid-cols-[1fr_auto] md:py-16">
            <div>
                <span class="inline-block rounded-full bg-accent-500 px-3 py-1 text-xs font-bold text-navy-900">{{ __('seller.landing.badge') }}</span>
                <h1 class="mt-4 text-2xl leading-10 font-bold md:text-4xl md:leading-[3.25rem]">{{ __('seller.landing.heading') }}</h1>
                <p class="mt-4 max-w-xl leading-8 text-white/80">{{ __('seller.landing.text') }}</p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ $ctaUrl }}" class="btn btn-accent px-6 text-base">
                        {{ $ctaLabel }}
                        @svg('heroicon-m-chevron-left', 'size-5')
                    </a>
                </div>
                @guest('customer')
                    <p class="mt-3 text-xs text-white/60">{{ __('seller.landing.login_note') }}</p>
                @endguest
            </div>
            <img src="{{ \App\Services\StoreInfo::DEFAULT_ICON }}" alt="" width="192" height="192" class="hidden size-48 md:block">
        </div>
    </section>

    {{-- چرا بازارک --}}
    <section class="container-page mt-10">
        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('seller.landing.benefits_heading') }}</h2>
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach (__('seller.landing.benefits') as $benefit)
                <li class="card p-4">
                    @svg($benefit['icon'], 'size-7 text-brand-600')
                    <h3 class="mt-2 text-sm font-bold text-gray-900">{{ $benefit['title'] }}</h3>
                    <p class="mt-1 text-xs leading-6 text-gray-500">{{ str_replace(':provinces', $deliveryText, $benefit['text']) }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- مزایای شروع (از «تنظیمات بازارگاه»؛ فقط آن‌هایی که روشن است) --}}
    @if (count($incentives) > 0)
        <section class="container-page mt-10">
            <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('commission.incentives.heading') }}</h2>
            <ul class="grid gap-3 md:grid-cols-3">
                @foreach ($incentives as $incentive)
                    <li class="card border-accent-200 bg-accent-50 p-4">
                        @svg(['social' => 'heroicon-o-camera', 'local' => 'heroicon-o-gift', 'brand' => 'heroicon-o-star'][$incentive['key']] ?? 'heroicon-o-sparkles', 'size-7 text-accent-700')
                        <h3 class="mt-2 text-sm font-bold text-gray-900">{{ $incentive['title'] }}</h3>
                        <p class="mt-1 text-xs leading-6 text-gray-700">{{ $incentive['text'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-500">{{ __('commission.incentives.note') }}</p>
        </section>
    @endif

    {{-- کمیسیون --}}
    <section class="container-page mt-10">
        <a href="{{ route('seller.commissions') }}" class="card flex items-center gap-4 p-5 hover:shadow-md">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                @svg('heroicon-o-receipt-percent', 'size-7')
            </span>
            <span class="flex-1">
                <span class="block font-bold text-gray-900">{{ __('commission.public.heading') }}</span>
                <span class="mt-1 block text-sm leading-7 text-gray-600">{{ __('commission.public.rules.0') }} {{ __('commission.public.rules.2') }}</span>
            </span>
            <span class="hidden text-sm font-medium text-brand-700 sm:inline">{{ __('commission.public.link') }}</span>
            @svg('heroicon-m-chevron-left', 'size-5 text-brand-700')
        </a>
    </section>

    {{-- قدم‌ها --}}
    <section class="container-page mt-10">
        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('seller.landing.steps_heading') }}</h2>
        <ol class="grid gap-3 md:grid-cols-4">
            @foreach (__('seller.landing.steps') as $text)
                <li class="card flex items-start gap-3 p-4">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-700 text-sm font-bold text-white">
                        {{ \App\Support\Num::toFa((string) $loop->iteration) }}
                    </span>
                    <p class="text-sm leading-7 text-gray-700">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- سؤال‌ها --}}
    <section class="container-page mt-10">
        <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('seller.landing.faq_heading') }}</h2>
        <div class="space-y-2">
            @foreach (__('seller.landing.faq') as $item)
                <details class="card group">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center gap-2 px-4 text-sm font-bold text-gray-900">
                        <span class="flex-1">{{ $item['q'] }}</span>
                        @svg('heroicon-m-chevron-down', 'size-5 text-gray-400 transition-transform group-open:rotate-180')
                    </summary>
                    <p class="border-t border-gray-100 px-4 py-3 text-sm leading-7 text-gray-600">{{ str_replace(':provinces', $deliveryText, $item['a']) }}</p>
                </details>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="{{ $ctaUrl }}" class="btn btn-primary px-8">{{ $ctaLabel }}</a>
        </div>
    </section>
@endsection
