{{--
    صفحه‌ی یک دسته: مسیر صفحه، زیردسته‌ها. اجناس در مرحله‌ی ۳ (کاتالوگ) به همین صفحه اضافه می‌شود.
--}}
@extends('storefront.layouts.app')

@php
    $name = $category->tr('name');
    $description = $category->tr('description');
@endphp

@section('title', $category->tr('meta_title') ?: $name)
@section('description', $category->tr('meta_description') ?: ($description ?: $name.' — '.$store->name()))
@section('canonical', $category->url())

@section('content')
    <div class="container-page py-6 md:py-8">
        {{-- مسیر صفحه --}}
        <nav aria-label="{{ __('store.categories.breadcrumb') }}" class="mb-4 text-xs text-gray-500">
            <ol class="flex flex-wrap items-center gap-1">
                <li><a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('store.nav.home') }}</a></li>
                @foreach ($ancestors as $ancestor)
                    <li class="flex items-center gap-1">
                        @svg('heroicon-m-chevron-left', 'size-3')
                        <a href="{{ $ancestor->url() }}" class="hover:text-brand-700">{{ $ancestor->tr('name') }}</a>
                    </li>
                @endforeach
                <li class="flex items-center gap-1" aria-current="page">
                    @svg('heroicon-m-chevron-left', 'size-3')
                    <span class="text-gray-700">{{ $name }}</span>
                </li>
            </ol>
        </nav>

        <h1 class="text-xl font-bold text-gray-900 md:text-2xl">{{ $name }}</h1>
        @if ($description !== '')
            <p class="mt-2 max-w-2xl text-sm leading-7 text-gray-600">{{ $description }}</p>
        @endif

        {{-- زیردسته‌ها --}}
        @if ($children->isNotEmpty())
            <section class="mt-6">
                <h2 class="sr-only">{{ __('store.categories.subcategories', ['name' => $name]) }}</h2>
                <ul class="flex gap-3 overflow-x-auto pb-2 md:flex-wrap md:overflow-visible">
                    @foreach ($children as $child)
                        <li class="shrink-0">
                            <a href="{{ $child->url() }}" class="flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-700 hover:border-brand-600 hover:text-brand-700">
                                @if ($child->image_path)
                                    <img src="{{ Storage::disk('public')->url($child->image_path) }}" alt="" width="28" height="28" loading="lazy" class="size-7 rounded-lg object-cover">
                                @elseif ($child->iconName())
                                    @svg($child->iconName(), 'size-5 text-brand-600')
                                @endif
                                {{ $child->tr('name') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- اجناس: مرحله‌ی ۳ --}}
        <section class="card mt-6 p-8 text-center">
            @svg('heroicon-o-cube', 'mx-auto size-12 text-gray-300')
            <h2 class="mt-3 font-bold text-gray-900">{{ __('store.categories.products_soon_heading') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-gray-500">{{ __('store.categories.products_soon_text') }}</p>
            @if ($store->whatsappLink())
                <a href="{{ $store->whatsappLink($name) }}" target="_blank" rel="noopener" class="btn btn-whatsapp mt-4">
                    @svg('heroicon-o-chat-bubble-left-right', 'size-5') {{ __('store.header.whatsapp') }}
                </a>
            @endif
        </section>
    </div>
@endsection
