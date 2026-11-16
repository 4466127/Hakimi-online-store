{{--
    صفحه‌بندی سایت مشتری: فارسی، راست‌چین، سبک و با اندازه‌ی لمس ۴۴ پیکسل.
    «قبلی»، شماره‌ی صفحه‌های اطراف صفحه‌ی فعلی، «بعدی».
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $numbers = collect([1, $last, $current - 1, $current, $current + 1])
            ->filter(fn ($n) => $n >= 1 && $n <= $last)->unique()->sort()->values();
    @endphp
    <nav role="navigation" aria-label="{{ __('store.listing.pagination') }}" class="mt-8 flex flex-wrap items-center justify-center gap-1.5 text-sm">
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-11 items-center rounded-xl px-3 text-gray-400" aria-disabled="true">{{ __('store.listing.prev') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-3 text-gray-700 hover:bg-gray-50">{{ __('store.listing.prev') }}</a>
        @endif

        @foreach ($numbers as $number)
            @if (! $loop->first && $number - $numbers[$loop->index - 1] > 1)
                <span class="px-1 text-gray-400" aria-hidden="true">…</span>
            @endif
            @if ($number === $current)
                <span aria-current="page" class="inline-flex size-11 items-center justify-center rounded-xl bg-brand-700 font-bold text-white">{{ \App\Support\Num::toFa((string) $number) }}</span>
            @else
                <a href="{{ $paginator->url($number) }}" class="inline-flex size-11 items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" aria-label="{{ __('store.listing.page', ['n' => \App\Support\Num::toFa((string) $number)]) }}">{{ \App\Support\Num::toFa((string) $number) }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-3 text-gray-700 hover:bg-gray-50">{{ __('store.listing.next') }}</a>
        @else
            <span class="inline-flex min-h-11 items-center rounded-xl px-3 text-gray-400" aria-disabled="true">{{ __('store.listing.next') }}</span>
        @endif
    </nav>
@endif
