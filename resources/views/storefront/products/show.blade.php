{{--
    صفحه‌ی جنس (الگو: دیجی‌کالا): مسیر صفحه، گالری، انتخاب نوع (سایز/رنگ) با Alpine، قیمت و تخفیف، موجودی،
    سفارش در واتساپ، مشخصات، توضیحات، جنس مرتبط. قیمت‌ها از سرور فرمت شده می‌آید (جاوااسکریپت فقط نشان می‌دهد).
    «افزودن به سبد» در مرحله‌ی ۴ فعال می‌شود.
--}}
@extends('storefront.layouts.app')

@php
    $name = $product->tr('name');
    $current = $data['current'];
    $short = trim(strip_tags($product->tr('short_description')));
    $descriptionText = trim($product->tr('description'));
    $images = $data['images'];
    $deliveryText = $store->deliveryProvincesText();
    $firstWhatsapp = $store->whatsappLink($name.' — '.$product->url());
    $swatch = fn (?string $hex): ?string => $hex && preg_match('/^#[0-9a-f]{6}$/i', $hex) ? $hex : null;
@endphp

@section('title', $product->tr('meta_title') ?: $name)
@section('description', $product->tr('meta_description') ?: ($short !== '' ? $short : $name.' — '.$store->name()))
@section('canonical', $product->url())
@section('ogType', 'product')
@if ($images !== [])
    @section('ogImage', $images[0]['url'])
@endif
@push('head')
    @if ($preview)<meta name="robots" content="noindex">@endif
    <script type="application/ld+json">{!! json_encode($data['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <div class="container-page py-4 md:py-8" x-data="productPicker(@js($data['picker']))">
        @if ($preview)
            <p class="alert alert-warning mb-4" role="status">{{ __('store.product.preview', ['status' => $product->statusLabel()]) }}</p>
        @endif
        <x-storefront.flash />

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
                <li class="flex items-center gap-1">
                    @svg('heroicon-m-chevron-left', 'size-3')
                    <a href="{{ $category->url() }}" class="hover:text-brand-700">{{ $category->tr('name') }}</a>
                </li>
            </ol>
        </nav>

        <div class="grid gap-6 md:grid-cols-2 md:items-start">
            {{-- گالری --}}
            <div class="card p-3" x-data="{ i: 0 }">
                <div class="relative aspect-square">
                    @forelse ($images as $n => $img)
                        <img @if ($n > 0) x-show="i === {{ $n }}" x-cloak @endif
                             @if ($n === 0) x-show="i === 0" @endif
                             src="{{ $img['url'] }}" srcset="{{ $img['srcset'] }}" sizes="(min-width: 768px) 34rem, 94vw"
                             width="{{ $img['width'] ?: 600 }}" height="{{ $img['height'] ?: 600 }}" alt="{{ $img['alt'] }}"
                             @if ($n === 0) fetchpriority="high" @else loading="lazy" @endif
                             class="absolute inset-0 size-full object-contain">
                    @empty
                        <div class="flex size-full items-center justify-center text-gray-300" aria-hidden="true">@svg('heroicon-o-photo', 'size-20')</div>
                    @endforelse

                    <span x-show="current && current.badge" @if (! ($current['badge'] ?? null)) x-cloak @endif
                          x-text="current ? current.badge : ''"
                          class="absolute start-2 top-2 rounded-full bg-red-600 px-2.5 py-1 text-sm font-bold text-white">{{ $current['badge'] ?? '' }}</span>
                </div>

                @if (count($images) > 1)
                    <ul class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($images as $n => $img)
                            <li class="shrink-0">
                                <button type="button" @click="i = {{ $n }}" :aria-current="i === {{ $n }}"
                                        :class="i === {{ $n }} ? 'border-brand-600' : 'border-gray-200'"
                                        class="block size-16 overflow-hidden rounded-xl border-2 bg-white"
                                        aria-label="{{ __('store.product.show_image', ['n' => \App\Support\Num::toFa((string) ($n + 1))]) }}">
                                    <img src="{{ $img['thumb'] }}" alt="" width="64" height="64" loading="lazy" class="size-full object-contain">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- معلومات و خرید --}}
            <div>
                @if ($product->brand)
                    <a href="{{ route('search', ['q' => $product->brand->name]) }}" class="text-sm font-medium text-brand-700 hover:underline" dir="auto">{{ $product->brand->name }}</a>
                @endif
                <h1 class="mt-1 text-xl leading-9 font-bold text-gray-900 md:text-2xl">{{ $name }}</h1>
                @if ($short !== '')
                    <p class="mt-2 text-sm leading-7 text-gray-600">{{ $short }}</p>
                @endif

                {{-- انتخاب نوع --}}
                @foreach ($data['groups'] as $group)
                    <div class="mt-5">
                        <p class="mb-2 text-sm font-medium text-gray-800">
                            {{ $group['name'] }}:
                            <span class="font-bold text-gray-900" x-text="selectedLabel({{ $group['id'] }})"></span>
                        </p>
                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ $group['name'] }}">
                            @foreach ($group['options'] as $option)
                                <button type="button" role="radio"
                                        :aria-checked="selected[{{ $group['id'] }}] === {{ $option['id'] }}"
                                        @click="pick({{ $group['id'] }}, {{ $option['id'] }})"
                                        :class="optionClass({{ $group['id'] }}, {{ $option['id'] }})"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-xl border px-3 text-sm transition-colors">
                                    @if ($swatch($option['hex']))
                                        <span class="size-5 rounded-full border border-gray-300" style="background: {{ $swatch($option['hex']) }}" aria-hidden="true"></span>
                                    @endif
                                    {{ $option['value'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- قیمت --}}
                <div class="mt-6 rounded-2xl bg-gray-50 p-4">
                    @if ($current)
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="text-2xl font-bold text-gray-900" x-text="current ? current.price : ''">{{ $current['price'] }}</p>
                            <p class="text-sm text-gray-400 line-through" x-show="current && current.compare" @if (! $current['compare']) x-cloak @endif x-text="current ? current.compare : ''">{{ $current['compare'] }}</p>
                        </div>
                        <p class="mt-2 text-sm text-gray-600" x-show="current && current.sku">
                            {{ __('store.product.sku') }}: <span dir="ltr" x-text="current ? current.sku : ''">{{ $current['sku'] }}</span>
                        </p>

                        <p x-show="current && current.available && ! current.low" @if (! $current['available'] || $current['low']) x-cloak @endif class="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-700">
                            @svg('heroicon-o-check-circle', 'size-5') {{ __('store.product.in_stock') }}
                        </p>
                        <p x-show="current && current.available && current.low" @if (! $current['low']) x-cloak @endif class="mt-2 flex items-center gap-1.5 text-sm font-medium text-accent-800">
                            @svg('heroicon-o-exclamation-triangle', 'size-5')
                            <span x-text="current ? current.leftText : ''">{{ $current['leftText'] }}</span>
                        </p>
                        <p x-show="! current || ! current.available" @if ($current['available']) x-cloak @endif class="mt-2 flex items-center gap-1.5 text-sm font-medium text-red-700">
                            @svg('heroicon-o-x-circle', 'size-5') {{ __('store.product.out_of_stock') }}
                        </p>
                    @else
                        <p class="text-sm font-medium text-red-700">{{ __('store.product.no_variant') }}</p>
                    @endif
                </div>

                {{-- خرید --}}
                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    @if ($current && Route::has('cart.add'))
                        <form method="post" action="{{ route('cart.add') }}" class="flex flex-1 gap-2">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $current['id'] }}" :value="current ? current.id : ''">
                            <label class="sr-only" for="add-qty">{{ __('cart.add_qty') }}</label>
                            <select id="add-qty" name="qty" class="form-input w-20 shrink-0 px-2" aria-label="{{ __('cart.add_qty') }}">
                                @for ($n = 1; $n <= 10; $n++)
                                    <option value="{{ $n }}">{{ \App\Support\Num::toFa((string) $n) }}</option>
                                @endfor
                            </select>
                            <button type="submit" class="btn btn-primary flex-1" :disabled="! current || ! current.available" @disabled(! $current['available'])>
                                @svg('heroicon-o-shopping-cart', 'size-5') {{ __('cart.add') }}
                            </button>
                        </form>
                    @elseif (! Route::has('cart.add'))
                        <button type="button" class="btn btn-primary flex-1" disabled aria-disabled="true" title="{{ __('store.header.soon') }}">
                            @svg('heroicon-o-shopping-cart', 'size-5') {{ __('store.product.add_to_cart') }} ({{ __('store.header.soon') }})
                        </button>
                    @endif
                    @if ($firstWhatsapp)
                        <a :href="whatsappHref()" href="{{ $firstWhatsapp }}" target="_blank" rel="noopener" class="btn btn-whatsapp flex-1">
                            @svg('heroicon-o-chat-bubble-left-right', 'size-5') {{ __('store.product.order_whatsapp') }}
                        </a>
                    @endif
                    @if ($store->telLink())
                        <a href="{{ $store->telLink() }}" class="btn btn-outline" aria-label="{{ __('store.header.call') }}">
                            @svg('heroicon-o-phone', 'size-5') <span class="sm:hidden">{{ __('store.header.call') }}</span>
                        </a>
                    @endif
                </div>

                {{-- اعتماد: فروشنده، تحویل، پرداخت --}}
                <ul class="mt-5 space-y-3 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        @svg('heroicon-o-building-storefront', 'mt-0.5 size-5 shrink-0 text-gray-400')
                        <span>{{ __('store.product.sold_by') }}: <strong>{{ $product->seller?->store_name ?: $store->name() }}</strong></span>
                    </li>
                    @if ($deliveryText !== '')
                        <li class="flex items-start gap-2">
                            @svg('heroicon-o-truck', 'mt-0.5 size-5 shrink-0 text-gray-400')
                            <span>{{ __('store.product.delivery', ['provinces' => $deliveryText]) }}</span>
                        </li>
                    @endif
                    @if ($store->pickupEnabled())
                        <li class="flex items-start gap-2">
                            @svg('heroicon-o-map-pin', 'mt-0.5 size-5 shrink-0 text-gray-400')
                            <span>{{ __('store.product.pickup') }} <span class="text-gray-500">({{ $store->pickupAddress() }})</span>@if ($store->pickupNote()) <span class="block text-xs text-gray-500">{{ $store->pickupNote() }}</span>@endif</span>
                        </li>
                    @endif
                    <li class="flex items-start gap-2">
                        @svg('heroicon-o-banknotes', 'mt-0.5 size-5 shrink-0 text-gray-400')
                        <span>{{ __('store.product.cod') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- توضیحات و مشخصات --}}
        @if ($descriptionText !== '' || $specs !== [])
            <div class="mt-8 grid gap-6 lg:grid-cols-2 lg:items-start">
                @if ($descriptionText !== '')
                    <section class="card p-5 md:p-6">
                        <h2 class="mb-3 text-lg font-bold text-gray-900">{{ __('store.product.description') }}</h2>
                        <div class="text-sm leading-8 text-gray-700">{!! nl2br(e($descriptionText)) !!}</div>
                    </section>
                @endif
                @if ($specs !== [])
                    <section class="card p-5 md:p-6">
                        <h2 class="mb-3 text-lg font-bold text-gray-900">{{ __('store.product.specs') }}</h2>
                        <dl class="divide-y divide-gray-100 text-sm">
                            @foreach ($specs as $spec)
                                <div class="flex gap-4 py-2.5">
                                    <dt class="w-2/5 shrink-0 text-gray-500">{{ $spec['name'] }}</dt>
                                    <dd class="font-medium text-gray-900">{{ $spec['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>
        @endif

        {{-- جنس مرتبط --}}
        @if ($related->isNotEmpty())
            <section class="mt-8">
                <h2 class="mb-3 text-lg font-bold text-gray-900">{{ __('store.product.related') }}</h2>
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($related as $item)
                        <li><x-storefront.product-card :product="$item" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
