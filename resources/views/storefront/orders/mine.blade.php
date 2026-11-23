{{-- «سفارش‌های من» برای مشتری عضو: لیست سفارش‌ها با وضعیت و لینک صفحه‌ی سفارش. --}}
@extends('storefront.layouts.app')

@section('title', __('checkout.mine.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@php
    use App\Support\Jalali;
    use App\Support\Money;
    use App\Support\Num;

    $statusColors = [
        'pending' => 'bg-accent-100 text-accent-900',
        'confirmed' => 'bg-brand-100 text-brand-900',
        'processing' => 'bg-brand-100 text-brand-900',
        'shipped' => 'bg-brand-100 text-brand-900',
        'delivered' => 'bg-green-100 text-green-900',
        'cancelled' => 'bg-red-100 text-red-900',
        'returned' => 'bg-gray-100 text-gray-800',
    ];
@endphp

@section('content')
    <div class="container-page py-6 md:py-10">
        <div class="mx-auto max-w-3xl">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ __('checkout.mine.heading') }}</h1>
                <a href="{{ route('account') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('customer.account.title') }}</a>
            </div>

            <x-storefront.flash />

            @if ($orders->isEmpty())
                <div class="card p-8 text-center">
                    @svg('heroicon-o-shopping-bag', 'mx-auto size-14 text-gray-300')
                    <h2 class="mt-3 font-bold text-gray-900">{{ __('checkout.mine.empty_heading') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-gray-500">{{ __('checkout.mine.empty_text') }}</p>
                    <a href="{{ route('home') }}" class="btn btn-primary mt-5">{{ __('cart.continue_shopping') }}</a>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($orders as $order)
                        <li class="card p-4 md:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-gray-900" dir="ltr">{{ $order->number }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ Jalali::dateTime($order->placed_at) }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ __('checkout.status.'.$order->status) }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-end justify-between gap-3 border-t border-gray-100 pt-3 text-sm">
                                <p class="text-gray-600">
                                    {{ __('checkout.mine.items_count', ['count' => Num::toFa((string) $order->items_count)]) }}
                                    • {{ $order->isPickup() ? __('checkout.delivery.pickup') : __('checkout.delivery.home') }}
                                </p>
                                <div class="flex items-center gap-4">
                                    <p class="font-bold text-gray-900">{{ Money::format($order->total) }}</p>
                                    <a href="{{ route('order.show', ['uuid' => $order->uuid]) }}" class="font-medium text-brand-700 hover:underline">
                                        {{ __('checkout.mine.view') }}
                                    </a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
@endsection
