{{--
    ویدیوی صفحه‌ی اصلی (۵ قوس ۱۴۰۵؛ الگو: ویدیوی جنس دیجی‌کالا): ویدیو، عنوان با لینک، اجناس زیرش.
    ویدیو بی صدا؛ فقط در دید پخش می‌شود؛ با «صرفه‌جویی داده» یا «حرکت کمتر» فقط عکس با دکمه‌ی پخش.
    ویدیو بریده نمی‌شود (object-contain روی زمینه‌ی سیاه) تا جنس کامل دیده شود.
    $video: HomeVideo
--}}
@php
    use App\Support\Money;
    use App\Support\Num;

    $title = $video->tr('title');
    $products = $video->products();
    $pricing = app(\App\Services\Catalog\PricingService::class);
@endphp

<section class="container-page mt-6 md:mt-8" aria-label="{{ $title }}">
    <div class="card overflow-hidden">
        <div class="relative bg-black" x-data="homeVideo()">
            <video x-ref="video" class="block aspect-video w-full object-contain md:aspect-auto md:h-[26rem]" poster="{{ $video->posterUrl() }}"
                   muted playsinline loop preload="none" disablepictureinpicture
                   aria-label="{{ __('store.video.label', ['title' => $title]) }}">
                <source src="{{ $video->videoUrl() }}" type="{{ $video->videoMime() }}">
            </video>

            {{-- پخش بزرگ وسط: وقتی خودکار پخش نمی‌شود (داده‌ی کم یا «حرکت کمتر») --}}
            <button type="button" x-show="manual && ! playing" x-cloak x-on:click="toggle()"
                    class="absolute inset-0 m-auto flex size-16 items-center justify-center rounded-full bg-black/60 text-white"
                    aria-label="{{ __('store.video.play') }}">
                @svg('heroicon-s-play', 'size-8 ltr:translate-x-0.5 rtl:-translate-x-0.5')
            </button>

            {{-- ایستاندن/پخش (گوشه‌ی بالا) و صدا (گوشه‌ی پایین)، مثل دیجی‌کالا --}}
            <button type="button" x-on:click="toggle()"
                    class="absolute end-3 top-3 flex size-11 items-center justify-center rounded-full bg-black/60 text-white"
                    :aria-label="playing ? @js(__('store.video.pause')) : @js(__('store.video.play'))">
                <span x-show="playing">@svg('heroicon-s-pause', 'size-5')</span>
                <span x-show="! playing">@svg('heroicon-s-play', 'size-5')</span>
            </button>
            <button type="button" x-on:click="toggleMute()"
                    class="absolute end-3 bottom-3 flex size-11 items-center justify-center rounded-full bg-black/60 text-white"
                    :aria-label="muted ? @js(__('store.video.unmute')) : @js(__('store.video.mute'))">
                <span x-show="muted">@svg('heroicon-o-speaker-x-mark', 'size-5')</span>
                <span x-show="! muted" x-cloak>@svg('heroicon-o-speaker-wave', 'size-5')</span>
            </button>
        </div>

        <div class="flex items-center justify-between gap-3 px-4 pt-3">
            @if ($video->link_url)
                <a href="{{ $video->link_url }}" @if ($video->isExternal()) rel="noopener" @endif
                   class="inline-flex min-h-11 items-center gap-1 rounded-full bg-gray-100 ps-4 pe-2 font-bold text-gray-900">
                    {{ $title }} @svg('heroicon-m-chevron-left', 'size-5 ltr:rotate-180')
                </a>
            @else
                <h2 class="py-2 font-bold text-gray-900">{{ $title }}</h2>
            @endif
        </div>

        @if ($products->isNotEmpty())
            {{-- اجناس زیر ویدیو: ردیف افقی (در موبایل با انگشت) --}}
            <ul class="flex snap-x gap-3 overflow-x-auto px-4 pt-3 pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($products as $product)
                    @php
                        $price = $pricing->forProduct($product);
                        $image = $product->primaryImage();
                    @endphp
                    <li class="w-64 shrink-0 snap-start">
                        <a href="{{ $product->url() }}" class="flex h-full items-center gap-3 rounded-xl border border-gray-200 p-2 hover:border-brand-600">
                            @if ($image)
                                <img src="{{ $image->url(300) }}" width="80" height="80" loading="lazy" alt="{{ $image->altText($product->tr('name')) }}"
                                     class="size-20 shrink-0 rounded-lg bg-white object-contain">
                            @else
                                <span class="flex size-20 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-300" aria-hidden="true">@svg('heroicon-o-photo', 'size-8')</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 text-sm leading-6 text-gray-800">{{ $product->tr('name') }}</span>
                                @if ($price)
                                    <span class="mt-1 flex flex-wrap items-center gap-2">
                                        @if ($price->hasDiscount())
                                            <span class="rounded-full bg-red-600 px-2 text-xs leading-5 font-bold text-white">{{ __('store.product.discount_badge', ['percent' => Num::toFa((string) $price->discountPercent)]) }}</span>
                                        @endif
                                        <span class="text-sm font-bold text-gray-900">{{ Money::format($price->price) }}</span>
                                    </span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="pb-2"></div>
        @endif
    </div>
</section>
