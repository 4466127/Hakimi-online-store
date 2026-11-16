{{-- ورود مشتری با شماره‌ی موبایل و رمز. --}}
@extends('storefront.layouts.app')

@section('title', __('customer.login.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container-page py-8 md:py-14">
        <div class="card mx-auto max-w-md p-6 md:p-8">
            <img src="{{ $store->iconUrl() }}" alt="" width="56" height="56" class="mx-auto size-14">
            <h1 class="mt-4 text-center text-xl font-bold text-gray-900">{{ __('customer.login.heading') }}</h1>
            <p class="mt-2 text-center text-sm leading-7 text-gray-600">{{ __('customer.login.intro') }}</p>

            <form method="post" action="{{ route('login') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <x-storefront.form-errors />

                <x-storefront.field name="phone" :label="__('customer.login.phone')" required
                                    :placeholder="__('customer.login.phone_placeholder')"
                                    type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="20" />

                <x-storefront.field name="password" :label="__('customer.login.password')" type="password" required
                                    autocomplete="current-password" dir="ltr" />

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="remember" value="1" class="form-check" @checked(old('remember', true))>
                    {{ __('customer.login.remember') }}
                </label>

                <button type="submit" class="btn btn-primary w-full">{{ __('customer.login.submit') }}</button>
            </form>

            <p class="mt-4 text-center text-xs leading-6 text-gray-500">{{ __('customer.login.forgot') }}</p>

            <p class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                {{ __('customer.login.no_account') }}
                <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">{{ __('customer.login.register_link') }}</a>
            </p>
        </div>
    </div>
@endsection
