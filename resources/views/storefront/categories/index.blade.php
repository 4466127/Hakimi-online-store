{{--
    همه‌ی دسته‌ها.
    موبایل (الگو: دیجی‌کالا، ۵ قوس ۱۴۰۵): نوار کنار با دسته‌های اصلی (آیکون + نام)، و کنارش «همه‌ی اجناس …»
    و زیرگروه‌های دسته‌ی انتخاب‌شده که هرکدام باز و بسته می‌شود. نوار کنار لینک `?c=slug` است، پس بی
    جاوااسکریپت هم کار می‌کند؛ با Alpine بی بارگذاری دوباره عوض می‌شود.
    کمپیوتر و تبلت: کارت‌های باز و بسته (مثل پیش).
--}}
@extends('storefront.layouts.app')

@section('title', __('store.categories.title'))
@section('description', __('store.categories.meta_description', ['name' => $store->name()]))

@section('content')
    @if (count($tree) === 0)
        <div class="container-page py-6 md:py-10">
            <h1 class="mb-5 text-xl font-bold text-gray-900">{{ __('store.categories.title') }}</h1>
            <p class="card p-6 text-center text-gray-500">{{ __('store.categories.empty') }}</p>
        </div>
    @else
        {{-- ===== موبایل: دو ستون ===== --}}
        <div class="md:hidden" x-data="{ active: {{ (int) $selected }} }">
            <h1 class="sr-only">{{ __('store.categories.title') }}</h1>
            <div class="flex items-start">
                {{-- نوار کنار: دسته‌های اصلی --}}
                <nav class="sticky top-24 max-h-[calc(100dvh-6rem)] w-24 shrink-0 overflow-y-auto bg-gray-100 pb-28 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                     aria-label="{{ __('store.categories.rail_label') }}">
                    <ul>
                        @foreach ($tree as $root)
                            <li>
                                <a href="{{ route('categories', ['c' => $root['slug']]) }}"
                                   x-on:click.prevent="active = {{ $loop->index }}; window.scrollTo({ top: 0 })"
                                   class="flex min-h-22 flex-col items-center justify-center gap-1.5 border-b border-gray-200/70 px-1.5 py-3 text-center text-[0.7rem] leading-5"
                                   :class="active === {{ $loop->index }} ? 'bg-white font-bold text-red-600' : 'text-gray-700'"
                                   :aria-current="active === {{ $loop->index }} ? 'true' : 'false'"
                                   @class(['bg-white font-bold text-red-600' => $loop->index === $selected, 'text-gray-700' => $loop->index !== $selected])>
                                    @svg($root['icon'] ?? 'heroicon-o-shopping-bag', 'size-6')
                                    <span class="line-clamp-2">{{ $root['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- زیرگروه‌های دسته‌ی انتخاب‌شده --}}
                <div class="min-w-0 flex-1 px-3 pt-2 pb-6">
                    @foreach ($tree as $root)
                        <section x-show="active === {{ $loop->index }}" @if ($loop->index !== $selected) style="display: none" @endif
                                 aria-label="{{ $root['name'] }}">
                            <a href="{{ route('category.show', ['slug' => $root['slug']]) }}"
                               class="flex min-h-12 items-center gap-1 border-b border-gray-100 text-sm font-bold text-sky-700">
                                {{ __('store.categories.show_all_products', ['name' => $root['name']]) }}
                                @svg('heroicon-m-chevron-left', 'size-4 ltr:rotate-180')
                            </a>

                            <ul>
                                @foreach ($root['children'] as $group)
                                    <li class="border-b border-gray-100">
                                        @if (count($group['children']) > 0)
                                            <details class="group">
                                                <summary class="flex min-h-14 cursor-pointer list-none items-center gap-2 text-sm font-medium text-gray-900 [&::-webkit-details-marker]:hidden">
                                                    <span class="flex-1">{{ $group['name'] }}</span>
                                                    @svg('heroicon-m-chevron-down', 'size-5 text-gray-500 transition-transform group-open:rotate-180')
                                                </summary>
                                                <ul class="pb-3">
                                                    <li>
                                                        <a href="{{ route('category.show', ['slug' => $group['slug']]) }}"
                                                           class="flex min-h-10 items-center text-sm font-medium text-sky-700">
                                                            {{ __('store.categories.show_all', ['name' => $group['name']]) }}
                                                        </a>
                                                    </li>
                                                    @foreach ($group['children'] as $item)
                                                        <li>
                                                            <a href="{{ route('category.show', ['slug' => $item['slug']]) }}"
                                                               class="flex min-h-10 items-center ps-3 text-sm text-gray-600">
                                                                {{ $item['name'] }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @else
                                            <a href="{{ route('category.show', ['slug' => $group['slug']]) }}"
                                               class="flex min-h-14 items-center gap-2 text-sm font-medium text-gray-900">
                                                <span class="flex-1">{{ $group['name'] }}</span>
                                                @svg('heroicon-m-chevron-left', 'size-5 text-gray-400 ltr:rotate-180')
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===== کمپیوتر و تبلت ===== --}}
        <div class="container-page hidden py-10 md:block">
            <h2 class="mb-5 text-xl font-bold text-gray-900" aria-hidden="true">{{ __('store.categories.title') }}</h2>
            <div class="space-y-3">
                @foreach ($tree as $root)
                    <details class="card group overflow-hidden" @if ($loop->index === $selected) open @endif>
                        <summary class="flex min-h-14 cursor-pointer list-none items-center gap-3 px-4 font-bold text-gray-900">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                                @svg($root['icon'] ?? 'heroicon-o-shopping-bag', 'size-5')
                            </span>
                            <span class="flex-1">{{ $root['name'] }}</span>
                            @svg('heroicon-m-chevron-down', 'size-5 text-gray-400 transition-transform group-open:rotate-180')
                        </summary>

                        <div class="border-t border-gray-100 px-4 py-4">
                            <a href="{{ route('category.show', ['slug' => $root['slug']]) }}" class="inline-flex min-h-10 items-center gap-1 text-sm font-medium text-brand-700">
                                {{ __('store.categories.show_all', ['name' => $root['name']]) }}
                                @svg('heroicon-m-chevron-left', 'size-4')
                            </a>

                            <div class="mt-3 grid gap-5 sm:grid-cols-2 md:grid-cols-3">
                                @foreach ($root['children'] as $group)
                                    <div>
                                        <a href="{{ route('category.show', ['slug' => $group['slug']]) }}" class="border-s-2 border-accent-500 ps-2 text-sm font-bold text-gray-900">
                                            {{ $group['name'] }}
                                        </a>
                                        @if (count($group['children']) > 0)
                                            <ul class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($group['children'] as $item)
                                                    <li>
                                                        <a href="{{ route('category.show', ['slug' => $item['slug']]) }}"
                                                           class="inline-flex min-h-9 items-center rounded-full border border-gray-200 px-3 text-xs text-gray-700 hover:border-brand-600 hover:text-brand-700">
                                                            {{ $item['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    @endif
@endsection
