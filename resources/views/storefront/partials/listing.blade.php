{{--
    لیست اجناس با فیلتر و مرتب‌سازی (مشترک بین صفحه‌ی دسته و جستجو). بی جاوااسکریپت هم کار می‌کند
    (فیلتر یک فورم GET است)؛ Alpine فقط دکمه‌ی «فیلتر» را در موبایل باز و بسته می‌کند.

    ورودی: $products (صفحه‌بندی‌شده)، $facets، $filters، $showRelevance (در جستجو «مرتبط‌ترین»)
--}}
@php
    $showRelevance = $showRelevance ?? false;
    $sorts = collect($showRelevance ? ['relevance'] : [])->merge(\App\Services\Catalog\ListingFilters::SORTS)->all();
    $defaultSort = $showRelevance ? 'relevance' : 'new';
    $clearUrl = url()->current().($filters->query !== '' ? '?'.http_build_query(['q' => $filters->query]) : '');
    $count = \App\Support\Num::toFa((string) $products->total());
@endphp

<div class="mt-6 md:grid md:grid-cols-[15rem_1fr] md:items-start md:gap-6" x-data="{ open: false }">
    {{-- فیلتر --}}
    <aside>
        <button type="button" class="btn btn-outline w-full md:hidden" @click="open = ! open" :aria-expanded="open">
            @svg('heroicon-o-adjustments-horizontal', 'size-5')
            {{ __('store.listing.filters') }}
            @if ($filters->activeCount() > 0)
                <span class="rounded-full bg-brand-700 px-2 text-xs text-white">{{ \App\Support\Num::toFa((string) $filters->activeCount()) }}</span>
            @endif
        </button>

        <form method="get" action="{{ url()->current() }}" :class="open ? '' : 'max-md:hidden'"
              class="card mt-3 space-y-5 p-4 md:mt-0" aria-label="{{ __('store.listing.filters') }}">
            @if ($filters->query !== '')
                <input type="hidden" name="q" value="{{ $filters->query }}">
            @endif
            @if ($filters->sort !== $defaultSort)
                <input type="hidden" name="sort" value="{{ $filters->sort }}">
            @endif

            {{-- قیمت --}}
            <fieldset>
                <legend class="mb-2 text-sm font-bold text-gray-900">{{ __('store.listing.price') }}</legend>
                <div class="flex items-center gap-2">
                    <input type="text" name="min" inputmode="decimal" dir="ltr" value="{{ $filters->minPrice !== null ? (int) $filters->minPrice : '' }}"
                           placeholder="{{ $facets['min'] !== null ? (int) $facets['min'] : __('store.listing.price_from') }}"
                           aria-label="{{ __('store.listing.price_from') }}" class="form-input min-w-0 text-center">
                    <span class="text-gray-400" aria-hidden="true">—</span>
                    <input type="text" name="max" inputmode="decimal" dir="ltr" value="{{ $filters->maxPrice !== null ? (int) $filters->maxPrice : '' }}"
                           placeholder="{{ $facets['max'] !== null ? (int) $facets['max'] : __('store.listing.price_to') }}"
                           aria-label="{{ __('store.listing.price_to') }}" class="form-input min-w-0 text-center">
                </div>
                <p class="form-help">{{ __('store.listing.price_help') }}</p>
            </fieldset>

            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="in_stock" value="1" class="form-check" @checked($filters->inStock)>
                {{ __('store.listing.in_stock_only') }}
            </label>

            {{-- برند --}}
            @if ($facets['brands']->isNotEmpty())
                <fieldset>
                    <legend class="mb-1 text-sm font-bold text-gray-900">{{ __('store.listing.brand') }}</legend>
                    <div class="max-h-56 overflow-y-auto">
                        @foreach ($facets['brands'] as $brand)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="brand[]" value="{{ $brand->id }}" class="form-check" @checked(in_array($brand->id, $filters->brandIds, true))>
                                <span dir="auto">{{ $brand->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- مشخصه‌های فیلترپذیر دسته (سایز، رنگ، حافظه…) --}}
            @foreach ($facets['attributes'] as $facet)
                <fieldset>
                    <legend class="mb-1 text-sm font-bold text-gray-900">{{ $facet['attribute']->tr('name') }}</legend>
                    <div class="max-h-56 overflow-y-auto">
                        @foreach ($facet['options'] as $option)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="f[{{ $facet['attribute']->id }}][]" value="{{ $option->id }}" class="form-check"
                                       @checked(in_array($option->id, $filters->options[$facet['attribute']->id] ?? [], true))>
                                @if ($option->color_hex && preg_match('/^#[0-9a-f]{6}$/i', $option->color_hex))
                                    <span class="size-4 shrink-0 rounded-full border border-gray-300" style="background: {{ $option->color_hex }}" aria-hidden="true"></span>
                                @endif
                                {{ $option->tr('value') }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary flex-1">{{ __('store.listing.apply') }}</button>
                @if ($filters->activeCount() > 0)
                    <a href="{{ $clearUrl }}" class="btn btn-outline">{{ __('store.listing.clear') }}</a>
                @endif
            </div>
        </form>
    </aside>

    {{-- نتیجه --}}
    <section class="mt-5 min-w-0 md:mt-0" aria-label="{{ __('store.listing.results') }}">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="text-sm text-gray-600">{{ __('store.listing.count', ['count' => $count]) }}</p>
            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 md:ms-auto" aria-label="{{ __('store.listing.sort') }}">
                @foreach ($sorts as $key)
                    @php $active = $filters->sort === $key; @endphp
                    <a href="{{ request()->fullUrlWithQuery(['sort' => $key === $defaultSort ? null : $key, 'page' => null]) }}"
                       @if ($active) aria-current="true" @endif
                       class="inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 text-sm {{ $active ? 'border-brand-700 bg-brand-50 font-bold text-brand-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ __('store.listing.sorts.'.$key) }}
                    </a>
                @endforeach
            </nav>
        </div>

        @if ($products->isEmpty())
            <div class="card mt-4 p-8 text-center">
                @svg('heroicon-o-magnifying-glass', 'mx-auto size-12 text-gray-300')
                <h2 class="mt-3 font-bold text-gray-900">{{ __('store.listing.empty_heading') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-gray-500">{{ __('store.listing.empty_text') }}</p>
                @if ($filters->activeCount() > 0)
                    <a href="{{ $clearUrl }}" class="btn btn-outline mt-4">{{ __('store.listing.clear') }}</a>
                @endif
            </div>
        @else
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($products as $product)
                    <li><x-storefront.product-card :product="$product" /></li>
                @endforeach
            </ul>
            {{ $products->links() }}
        @endif
    </section>
</div>
