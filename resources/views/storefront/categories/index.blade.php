{{--
    همه‌ی دسته‌ها (بیشتر برای موبایل؛ از نوار پایین «دسته‌ها»).
    هر دسته‌ی اصلی باز و بسته می‌شود (بی جاوااسکریپت، با details).
--}}
@extends('storefront.layouts.app')

@section('title', __('store.categories.title'))
@section('description', __('store.categories.meta_description', ['name' => $store->name()]))

@section('content')
    <div class="container-page py-6 md:py-10">
        <h1 class="mb-5 text-xl font-bold text-gray-900">{{ __('store.categories.title') }}</h1>

        @if (count($tree) === 0)
            <p class="card p-6 text-center text-gray-500">{{ __('store.categories.empty') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($tree as $root)
                    <details class="card group overflow-hidden" @if ($loop->first) open @endif>
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
        @endif
    </div>
@endsection
