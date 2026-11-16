{{-- نرخ کمیسیون بازارک برای هر دسته و قاعده‌های کمیسیون (فروشنده پیش از گذاشتن جنس می‌بیند). --}}
@extends('storefront.layouts.app')

@section('title', __('commission.public.title'))
@section('description', __('commission.public.meta_description'))

@section('content')
    <div class="container-page max-w-3xl py-6 md:py-10">
        <nav class="mb-4 text-xs text-gray-500" aria-label="{{ __('store.categories.breadcrumb') }}">
            <a href="{{ route('seller.landing') }}" class="hover:text-brand-700">{{ __('seller.landing.title') }}</a>
            <span aria-hidden="true">‹</span>
            <span class="text-gray-700">{{ __('commission.public.title') }}</span>
        </nav>

        <h1 class="text-xl font-bold text-gray-900 md:text-2xl">{{ __('commission.public.heading') }}</h1>
        <p class="mt-3 text-sm leading-7 text-gray-600">{{ __('commission.public.intro') }}</p>

        {{-- جدول نرخ‌ها --}}
        <div class="card mt-6 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-medium">{{ __('commission.public.table_category') }}</th>
                        <th scope="col" class="w-28 px-4 py-3 text-center font-medium">{{ __('commission.public.table_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-start font-bold text-gray-900">{{ $row['name'] }}</th>
                            <td class="px-4 py-3 text-center font-bold text-brand-700">{{ \App\Support\Num::format($row['rate'], 2) }}٪</td>
                        </tr>
                        @foreach ($row['children'] as $child)
                            <tr class="bg-gray-50/50">
                                <th scope="row" class="py-2.5 ps-8 pe-4 text-start font-normal text-gray-700">— {{ $child['name'] }}</th>
                                <td class="px-4 py-2.5 text-center text-brand-700">{{ \App\Support\Num::format($child['rate'], 2) }}٪</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr>
                        <th scope="row" class="px-4 py-3 text-start font-bold text-gray-900">{{ __('commission.public.other') }}</th>
                        <td class="px-4 py-3 text-center font-bold text-brand-700">{{ \App\Support\Num::format($defaultRate, 2) }}٪</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- مثال --}}
        <div class="alert alert-success mt-5">
            <p class="font-bold">{{ __('commission.public.example_heading') }}</p>
            @php
                // مثال از نرخ واقعی اولین دسته (نه عدد ثابت)
                $exampleRow = $rows[0] ?? null;
                $exampleRate = $exampleRow['children'][0]['rate'] ?? $exampleRow['rate'] ?? $defaultRate;
                $exampleName = $exampleRow['children'][0]['name'] ?? $exampleRow['name'] ?? __('commission.public.other');
                $exampleCommission = \App\Support\Money::percentOf('10000', $exampleRate);
            @endphp
            <p class="mt-1">{{ __('commission.public.example', [
                'name' => $exampleName,
                'rate' => \App\Support\Num::format($exampleRate, 2),
                'commission' => \App\Support\Num::format($exampleCommission, 0),
                'seller' => \App\Support\Num::format(\App\Support\Money::sub('10000', $exampleCommission), 0),
            ]) }}</p>
        </div>

        {{-- قاعده‌ها --}}
        <section class="card mt-6 p-5 md:p-6">
            <h2 class="font-bold text-gray-900">{{ __('commission.public.rules_heading') }}</h2>
            <ul class="mt-3 space-y-2">
                @foreach (__('commission.public.rules') as $rule)
                    <li class="flex items-start gap-2 text-sm leading-7 text-gray-700">
                        @svg('heroicon-o-check-circle', 'mt-1 size-5 shrink-0 text-brand-600')
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="mt-8 text-center">
            <a href="{{ route('seller.onboarding') }}" class="btn btn-accent px-8">{{ __('seller.landing.cta') }}</a>
        </div>
    </div>
@endsection
