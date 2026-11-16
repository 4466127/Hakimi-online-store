{{-- ثبت‌نام مشتری: نام، شماره‌ی موبایل، رمز. --}}
@extends('storefront.layouts.app')

@section('title', __('customer.register.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container-page py-8 md:py-14">
        <div class="card mx-auto max-w-md p-6 md:p-8">
            <img src="{{ $store->iconUrl() }}" alt="" width="56" height="56" class="mx-auto size-14">
            <h1 class="mt-4 text-center text-xl font-bold text-gray-900">{{ __('customer.register.heading') }}</h1>
            <p class="mt-2 text-center text-sm leading-7 text-gray-600">{{ __('customer.register.intro') }}</p>

            <form method="post" action="{{ route('register') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <x-storefront.form-errors />

                <x-storefront.field name="name" :label="__('customer.register.name')" required
                                    :placeholder="__('customer.register.name_placeholder')" autocomplete="name" maxlength="100" />

                <x-storefront.field name="phone" :label="__('customer.register.phone')" required
                                    :placeholder="__('customer.register.phone_placeholder')" :help="__('customer.register.phone_help')"
                                    type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="20" />

                <x-storefront.field name="password" :label="__('customer.register.password')" type="password" required
                                    :help="__('customer.register.password_help')" autocomplete="new-password" dir="ltr" />

                <x-storefront.field name="password_confirmation" :label="__('customer.register.password_confirmation')" type="password" required
                                    autocomplete="new-password" dir="ltr" />

                <div>
                    <label class="flex items-start gap-2 text-sm leading-6 text-gray-700">
                        <input type="checkbox" name="terms" value="1" class="form-check mt-0.5" @checked(old('terms')) required>
                        <span>
                            {{ __('customer.register.terms') }}
                            @if ($terms && $terms->hasBody())
                                <a href="{{ $terms->url() }}" target="_blank" class="text-brand-700 underline">{{ __('customer.register.terms_link') }}</a>
                            @endif
                        </span>
                    </label>
                    @error('terms')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full">{{ __('customer.register.submit') }}</button>
            </form>

            <p class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
                {{ __('customer.register.have_account') }}
                <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">{{ __('customer.register.login_link') }}</a>
            </p>
        </div>
    </div>
@endsection
