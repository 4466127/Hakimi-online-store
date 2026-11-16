{{--
    قالب اصلی همه‌ی صفحه‌های مشتری: سربرگ، متن صفحه، پاورقی، نوار پایین موبایل.
    هر صفحه می‌تواند بدهد: title ، description ، canonical ، ogImage و بخش head.
--}}
@inject('store', 'App\Services\StoreInfo')
@php
    $locale = app()->getLocale();
    $dir = in_array($locale, config('store.rtl_locales', ['fa']), true) ? 'rtl' : 'ltr';
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' | '.$store->name() : $store->name();
    $description = trim($__env->yieldContent('description')) ?: ($store->tagline() ?? $store->name());
    $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
    $ogImage = trim($__env->yieldContent('ogImage')) ?: $store->iconUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#16324f">

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">

    {{-- پیش‌نمایش لینک در واتساپ و شبکه‌های اجتماعی --}}
    <meta property="og:site_name" content="{{ $store->name() }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="@yield('ogType', 'website')">
    <meta property="og:locale" content="fa_AF">
    @if ($ogImage)
        <meta property="og:image" content="{{ url($ogImage) }}">
    @endif

    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preload" href="/fonts/vazirmatn/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        {{ __('store.skip_to_content') }}
    </a>

    <x-storefront.header :store="$store" />

    <main id="main" class="flex-1 pb-10">
        @yield('content')
    </main>

    <x-storefront.footer :store="$store" />
    <x-storefront.bottom-nav />

    @stack('scripts')
</body>
</html>
