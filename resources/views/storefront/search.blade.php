{{--
    جستجو: نام، برند، دسته، SKU و مشخصات. نتیجه با همان فیلتر و مرتب‌سازی صفحه‌ی دسته.
    جستجوی خالی: دسته‌ها و تازه‌ترین اجناس. صفحه‌ی جستجو برای گوگل نیست (noindex).
--}}
@extends('storefront.layouts.app')

@section('title', $filters->query !== '' ? __('store.search.title_for', ['q' => $filters->query]) : __('store.search.title'))
@section('description', __('store.search.meta_description', ['name' => $store->name()]))
@push('head')<meta name="robots" content="noindex,follow">@endpush

@section('content')
    <div class="container-page py-6 md:py-8">
        {{-- فورم جستجو (در موبایل سربرگ هم دارد؛ اینجا برای نوشتن راحت‌تر و جستجوی دوباره) --}}
        <form action="{{ route('search') }}" method="get" role="search" class="max-w-2xl">
            <label for="search-q" class="sr-only">{{ __('store.nav.search') }}</label>
            <div class="flex gap-2">
                <input id="search-q" type="search" name="q" value="{{ $filters->query }}" maxlength="100" @if ($filters->query === '') autofocus @endif
                       placeholder="{{ __('store.search.placeholder') }}" class="form-input flex-1">
                <button type="submit" class="btn btn-primary">{{ __('store.search.button') }}</button>
            </div>
        </form>

        @if ($products === null)
            {{-- جستجوی خالی یا یک‌حرفی --}}
            @if ($filters->query !== '')
                <p class="mt-4 text-sm text-gray-600">{{ __('store.search.too_short') }}</p>
            @else
                <p class="mt-4 text-sm text-gray-600">{{ __('store.search.hint') }}</p>
            @endif

            @if (count($tree) > 0)
                <section class="mt-6">
                    <h2 class="mb-3 font-bold text-gray-900">{{ __('store.home.categories_heading') }}</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($tree as $root)
                            <li>
                                <a href="{{ route('category.show', ['slug' => $root['slug']]) }}" class="inline-flex min-h-11 items-center rounded-full border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:border-brand-600 hover:text-brand-700">{{ $root['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($latest->isNotEmpty())
                <section class="mt-8">
                    <h2 class="mb-3 font-bold text-gray-900">{{ __('store.home.latest_heading') }}</h2>
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        @foreach ($latest as $product)
                            <li><x-storefront.product-card :product="$product" /></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @else
            <h1 class="mt-5 text-lg font-bold text-gray-900 md:text-xl">{{ __('store.search.results_for', ['q' => $filters->query]) }}</h1>

            @if ($categories->isNotEmpty())
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-500">{{ __('store.search.in_categories') }}</span>
                    @foreach ($categories as $category)
                        <a href="{{ $category->url() }}" class="inline-flex min-h-11 items-center rounded-full border border-gray-300 bg-white px-4 text-sm text-gray-700 hover:border-brand-600 hover:text-brand-700">{{ $category->tr('name') }}</a>
                    @endforeach
                </div>
            @endif

            @include('storefront.partials.listing', ['showRelevance' => true])
        @endif
    </div>
@endsection
