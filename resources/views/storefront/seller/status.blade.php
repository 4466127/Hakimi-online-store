{{-- وضعیت درخواست فروشندگی: منتظر بررسی، تأییدشده، ردشده، بسته‌شده. --}}
@extends('storefront.layouts.app')

@section('title', __('seller.status.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php
        $status = $seller->status->value;
        $look = [
            'pending' => ['heroicon-o-clock', 'bg-accent-50 text-accent-700'],
            'approved' => ['heroicon-o-check-circle', 'bg-brand-50 text-brand-700'],
            'rejected' => ['heroicon-o-x-circle', 'bg-red-50 text-red-700'],
            'suspended' => ['heroicon-o-exclamation-triangle', 'bg-red-50 text-red-700'],
        ][$status] ?? ['heroicon-o-clock', 'bg-gray-100 text-gray-600'];
    @endphp

    <div class="container-page max-w-3xl py-6 md:py-10">
        <x-storefront.flash />

        <div class="card p-6 text-center md:p-8">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full {{ $look[1] }}">
                @svg($look[0], 'size-9')
            </span>
            <p class="mt-3 text-xs font-medium text-gray-500">{{ __('seller.status.title') }}: {{ $seller->status->label() }}</p>

            @if ($status === 'pending')
                <h1 class="mt-2 text-xl font-bold text-gray-900">{{ __('seller.status.pending_heading') }}</h1>
                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-gray-600">
                    {{ __('seller.status.pending_text', [
                        'date' => $seller->submitted_at ? \App\Support\Jalali::date($seller->submitted_at) : '',
                        'phone' => \App\Support\Phone::display($seller->customer->phone),
                    ]) }}
                </p>
            @elseif ($status === 'approved')
                <h1 class="mt-2 text-xl font-bold text-gray-900">{{ __('seller.status.approved_heading') }}</h1>
                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-gray-600">{{ __('seller.status.approved_text') }}</p>
                <span class="btn btn-outline mt-5" aria-disabled="true">
                    @svg('heroicon-m-lock-closed', 'size-4') {{ __('seller.status.panel') }} ({{ __('seller.status.panel_soon') }})
                </span>
            @elseif ($status === 'rejected')
                <h1 class="mt-2 text-xl font-bold text-gray-900">{{ __('seller.status.rejected_heading') }}</h1>
                @if ($seller->review_note)
                    <p class="alert alert-error mx-auto mt-4 max-w-lg text-start">{{ __('seller.status.rejected_reason', ['reason' => $seller->review_note]) }}</p>
                @endif
                <p class="mt-3 text-sm text-gray-600">{{ __('seller.status.rejected_text') }}</p>
                <a href="{{ route('seller.step', ['step' => 'info']) }}" class="btn btn-primary mt-5">
                    @svg('heroicon-o-pencil-square', 'size-5') {{ __('seller.status.edit') }}
                </a>
            @else
                <h1 class="mt-2 text-xl font-bold text-gray-900">{{ __('seller.status.suspended_heading') }}</h1>
                @if ($seller->review_note)
                    <p class="alert alert-error mx-auto mt-4 max-w-lg text-start">{{ __('seller.status.rejected_reason', ['reason' => $seller->review_note]) }}</p>
                @endif
                <p class="mt-3 text-sm text-gray-600">{{ __('seller.status.suspended_text') }}</p>
            @endif
        </div>

        {{-- حساب و کمیسیون (فروشنده‌ی تأییدشده) --}}
        @if ($seller->approved_at !== null)
            @php
                $commission = app(\App\Services\Commission\CommissionService::class);
                $overrides = $commission->activeOverrides($seller);
            @endphp
            <section class="card mt-6 p-5 md:p-6">
                <h2 class="font-bold text-gray-900">{{ __('commission.seller_view.heading') }}</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-brand-50 p-4">
                        <dt class="text-xs text-brand-800">{{ __('commission.seller_view.balance') }}</dt>
                        <dd class="mt-1 text-xl font-bold text-brand-900">{{ \App\Support\Num::format($commission->balance($seller), 0) }} {{ __('admin.finance.afn') }}</dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4">
                        <dt class="text-xs text-gray-600">{{ __('commission.seller_view.cycle') }}</dt>
                        <dd class="mt-1 font-bold text-gray-900">{{ __('commission.cycles.'.$seller->settlement_cycle) }}</dd>
                    </div>
                </dl>
                <ul class="mt-4 space-y-2 text-sm text-gray-700">
                    @foreach ($overrides as $override)
                        <li class="flex items-center gap-2">
                            @svg('heroicon-o-sparkles', 'size-5 text-accent-600')
                            {{ $override->ends_at
                                ? __('commission.seller_view.special_rate', ['rate' => \App\Support\Num::format((string) $override->rate, 2), 'date' => \App\Support\Jalali::date($override->ends_at)])
                                : __('commission.seller_view.special_rate_forever', ['rate' => \App\Support\Num::format((string) $override->rate, 2)]) }}
                            @if ($override->category)
                                ({{ $override->category->tr('name') }})
                            @endif
                        </li>
                    @endforeach
                    @if ($seller->hasFreePickup())
                        <li class="flex items-center gap-2">
                            @svg('heroicon-o-truck', 'size-5 text-accent-600')
                            {{ __('commission.seller_view.free_pickup', ['date' => \App\Support\Jalali::date($seller->free_pickup_until)]) }}
                        </li>
                    @endif
                </ul>
                <a href="{{ route('seller.commissions') }}" class="mt-4 inline-flex min-h-10 items-center gap-1 text-sm font-medium text-brand-700">
                    {{ __('commission.seller_view.rates_link') }} @svg('heroicon-m-chevron-left', 'size-4')
                </a>
            </section>
        @endif

        <section class="card mt-6 p-5 md:p-6">
            <h2 class="font-bold text-gray-900">{{ __('seller.status.summary') }}</h2>
            @include('storefront.seller.partials.summary', ['seller' => $seller, 'editable' => false])
        </section>
    </div>
@endsection
