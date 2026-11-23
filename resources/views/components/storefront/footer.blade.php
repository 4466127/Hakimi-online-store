{{-- پاورقی: درباره‌ی فروشگاه، تماس، لینک صفحه‌های ثابت. همه از تنظیمات پنل می‌آید. --}}
@props(['store'])
@php $pages = $store->footerPages(); @endphp

<footer class="border-t border-gray-200 bg-white pb-20 md:pb-0">
    <div class="container-page grid gap-8 py-10 md:grid-cols-3">
        <section>
            <h2 class="mb-3">
                <img src="{{ $store->logoUrl() }}" alt="{{ $store->name() }}" width="140" height="40" class="h-10 w-auto" loading="lazy">
            </h2>
            @if ($store->tagline())
                <p class="text-sm leading-7 text-gray-600">{{ $store->tagline() }}</p>
            @endif
            @if ($store->address())
                <p class="mt-3 flex items-start gap-2 text-sm leading-7 text-gray-600">
                    @svg('heroicon-o-map-pin', 'mt-1 size-5 shrink-0 text-gray-400')
                    <span>{{ $store->address() }}</span>
                </p>
            @endif
        </section>

        <section>
            <h2 class="mb-3 font-bold text-gray-900">{{ __('store.footer.contact') }}</h2>
            @if ($store->hasContact() || $store->email())
                <ul class="space-y-2 text-sm text-gray-600">
                    @if ($store->telLink())
                        <li>
                            <a href="{{ $store->telLink() }}" class="inline-flex min-h-11 items-center gap-2 hover:text-brand-700">
                                @svg('heroicon-o-phone', 'size-5 text-gray-400')
                                <span>{{ __('store.footer.phone') }}:</span>
                                <span dir="ltr">{{ $store->phoneDisplay() }}</span>
                            </a>
                        </li>
                    @endif
                    @if ($store->whatsappLink())
                        <li>
                            <a href="{{ $store->whatsappLink() }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 hover:text-brand-700">
                                @svg('heroicon-o-chat-bubble-left-right', 'size-5 text-gray-400')
                                <span>{{ __('store.footer.whatsapp') }}:</span>
                                <span dir="ltr">{{ $store->whatsappDisplay() }}</span>
                            </a>
                        </li>
                    @endif
                    @if ($store->email())
                        <li>
                            <a href="mailto:{{ $store->email() }}" class="inline-flex min-h-11 items-center gap-2 hover:text-brand-700">
                                @svg('heroicon-o-envelope', 'size-5 text-gray-400')
                                <span dir="ltr">{{ $store->email() }}</span>
                            </a>
                        </li>
                    @endif
                </ul>
            @else
                <p class="text-sm text-gray-500">{{ __('store.footer.no_contact') }}</p>
            @endif
        </section>

        <section>
            <h2 class="mb-3 font-bold text-gray-900">{{ __('store.footer.pages') }}</h2>
            <ul class="space-y-1 text-sm text-gray-600">
                <li>
                    <a href="{{ route('seller.landing') }}" class="inline-flex min-h-11 items-center gap-1.5 font-medium text-accent-700 hover:text-accent-800">
                        @svg('heroicon-o-building-storefront', 'size-5')
                        {{ __('store.footer.sell') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('order.track') }}" class="inline-flex min-h-11 items-center gap-1.5 hover:text-brand-700">
                        @svg('heroicon-o-magnifying-glass', 'size-5 text-gray-400')
                        {{ __('checkout.track.title') }}
                    </a>
                </li>
                @foreach ($pages as $page)
                    <li>
                        <a href="{{ $page['url'] }}" class="inline-flex min-h-11 items-center hover:text-brand-700">{{ $page['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="border-t border-gray-100">
        <p class="container-page py-4 text-center text-xs text-gray-500">
            {{ __('store.footer.rights', ['name' => $store->name()]) }}
        </p>
    </div>
</footer>
