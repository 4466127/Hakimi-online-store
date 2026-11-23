{{-- پیگیری سفارش برای مهمان: شماره‌ی سفارش + شماره‌ی موبایل. --}}
@extends('storefront.layouts.app')

@section('title', __('checkout.track.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container-page py-8 md:py-12">
        <div class="mx-auto max-w-md">
            <h1 class="text-xl font-bold text-gray-900">{{ __('checkout.track.heading') }}</h1>
            <p class="mt-2 text-sm leading-7 text-gray-600">{{ __('checkout.track.text') }}</p>

            <x-storefront.flash />
            <x-storefront.form-errors />

            <form method="post" action="{{ route('order.track.submit') }}" class="card mt-5 space-y-4 p-5 md:p-6" novalidate>
                @csrf
                <x-storefront.field name="number" :label="__('checkout.track.number')" required maxlength="30" dir="ltr"
                                    :placeholder="__('checkout.track.number_placeholder')" :help="__('checkout.track.number_help')" />
                <x-storefront.field name="phone" :label="__('checkout.track.phone')" required maxlength="20" dir="ltr"
                                    inputmode="tel" autocomplete="tel" :placeholder="__('checkout.purchaser.phone_placeholder')" />
                <button type="submit" class="btn btn-primary w-full">{{ __('checkout.track.submit') }}</button>
            </form>

            @auth('customer')
                <p class="mt-4 text-center text-sm">
                    <a href="{{ route('account.orders') }}" class="font-medium text-brand-700 hover:underline">{{ __('checkout.order.my_orders') }}</a>
                </p>
            @endauth
        </div>
    </div>
@endsection
