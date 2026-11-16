{{--
    کارت جنس در لیست‌ها (دسته، جستجو، صفحه‌ی اصلی، جنس مرتبط).
    عکس سبک با srcset (موبایل ۳۰۰ پیکسل می‌گیرد)، نشان تخفیف، «ناموجود» و قیمت (با «از» برای چند نوع).
    $product باید با images و brand بارگذاری شده باشد (بی N+1).
--}}
@props(['product'])
@php
    $price = app(\App\Services\Catalog\PricingService::class)->forProduct($product);
    $image = $product->primaryImage();
    $inStock = $product->isInStock();
@endphp

<a href="{{ $product->url() }}" class="card group relative flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md">
    <div class="relative bg-white">
        @if ($image)
            <img src="{{ $image->url(300) }}" srcset="{{ $image->srcset() }}"
                 sizes="(min-width: 1024px) 14rem, (min-width: 640px) 30vw, 46vw"
                 width="300" height="300" loading="lazy" alt="{{ $image->altText($product->tr('name')) }}"
                 class="aspect-square w-full object-contain p-2 {{ $inStock ? '' : 'opacity-50' }}">
        @else
            <div class="flex aspect-square w-full items-center justify-center text-gray-300" aria-hidden="true">
                @svg('heroicon-o-photo', 'size-12')
            </div>
        @endif

        @if ($price?->hasDiscount())
            <span class="absolute start-2 top-2 rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">
                {{ __('store.product.discount_badge', ['percent' => \App\Support\Num::toFa((string) $price->discountPercent)]) }}
            </span>
        @endif

        @unless ($inStock)
            <span class="absolute inset-x-2 bottom-2 rounded-lg bg-gray-800/80 px-2 py-1 text-center text-xs text-white">{{ __('store.product.out_of_stock') }}</span>
        @endunless
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 min-h-12 text-sm leading-6 font-medium text-gray-900 group-hover:text-brand-700">{{ $product->tr('name') }}</h3>

        @if ($price)
            <div class="mt-auto pt-1">
                @if ($price->isRange)
                    <span class="text-xs text-gray-500">{{ __('store.product.from') }}</span>
                @endif
                <p class="text-sm font-bold {{ $inStock ? 'text-gray-900' : 'text-gray-400' }}">{{ \App\Support\Money::format($price->price) }}</p>
                @if ($price->hasDiscount())
                    <p class="text-xs text-gray-400 line-through">{{ \App\Support\Money::format($price->comparePrice) }}</p>
                @endif
            </div>
        @endif
    </div>
</a>
