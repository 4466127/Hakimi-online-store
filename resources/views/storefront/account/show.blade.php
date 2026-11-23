{{-- حساب کاربری مشتری: خلاصه، فروشندگی، ویرایش معلومات، تغییر رمز. --}}
@extends('storefront.layouts.app')

@section('title', __('customer.account.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container-page py-6 md:py-10">
        <div class="grid gap-6 md:grid-cols-[16rem_1fr]">
            {{-- کنار: نام و منوی حساب --}}
            <aside class="card h-fit p-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                        @svg('heroicon-o-user', 'size-6')
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-bold text-gray-900">{{ $customer->name }}</p>
                        <p class="text-sm text-gray-500" dir="ltr">{{ \App\Support\Phone::display($customer->phone) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    {{ __('customer.account.member_since', ['date' => \App\Support\Jalali::date($customer->created_at)]) }}
                </p>

                <nav class="mt-4 border-t border-gray-100 pt-3 text-sm" aria-label="{{ __('customer.account.title') }}">
                    <ul class="space-y-1">
                        <li>
                            <a href="{{ route('account') }}" aria-current="page" class="flex min-h-11 items-center gap-2 rounded-lg bg-brand-50 px-3 font-medium text-brand-800">
                                @svg('heroicon-o-user-circle', 'size-5') {{ __('customer.account.menu.overview') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('account.orders') }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 text-gray-700 hover:bg-gray-50">
                                @svg('heroicon-o-shopping-bag', 'size-5') {{ __('customer.account.menu.orders') }}
                            </a>
                        </li>
                        <li>
                            <span class="flex min-h-11 items-center gap-2 px-3 text-gray-400" aria-disabled="true">
                                @svg('heroicon-o-map-pin', 'size-5') {{ __('customer.account.menu.addresses') }}
                                <span class="ms-auto rounded-full bg-gray-100 px-2 text-xs">{{ __('customer.account.soon') }}</span>
                            </span>
                        </li>
                        <li>
                            <a href="{{ $seller ? route('seller.onboarding') : route('seller.landing') }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 text-gray-700 hover:bg-gray-50">
                                @svg('heroicon-o-building-storefront', 'size-5') {{ __('customer.account.menu.seller') }}
                            </a>
                        </li>
                    </ul>
                </nav>

                <form method="post" action="{{ route('logout') }}" class="mt-3 border-t border-gray-100 pt-3">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 text-sm text-red-700 hover:bg-red-50">
                        @svg('heroicon-o-arrow-right-on-rectangle', 'size-5') {{ __('customer.account.logout') }}
                    </button>
                </form>
            </aside>

            <div class="min-w-0 space-y-6">
                <x-storefront.flash />

                <h1 class="text-xl font-bold text-gray-900">{{ __('customer.account.hello', ['name' => $customer->name]) }}</h1>

                {{-- فروشندگی --}}
                @if ($seller)
                    <a href="{{ route('seller.onboarding') }}" class="card flex items-center gap-4 p-5 hover:shadow-md">
                        @svg('heroicon-o-building-storefront', 'size-8 shrink-0 text-accent-600')
                        <div class="flex-1">
                            <p class="font-bold text-gray-900">{{ $seller->store_name ?: __('customer.account.menu.seller') }}</p>
                            <p class="mt-1 text-sm text-gray-600">{{ __('customer.account.seller_status', ['status' => $seller->status->label()]) }}</p>
                        </div>
                        <span class="text-sm font-medium text-brand-700">{{ __('customer.account.seller_open') }}</span>
                    </a>
                @else
                    <div class="flex flex-col items-start gap-4 rounded-2xl bg-navy-800 p-5 text-white sm:flex-row sm:items-center">
                        @svg('heroicon-o-building-storefront', 'size-8 shrink-0 text-accent-400')
                        <div class="flex-1">
                            <p class="font-bold">{{ __('customer.account.seller_cta_title') }}</p>
                            <p class="mt-1 text-sm text-white/80">{{ __('customer.account.seller_cta_text') }}</p>
                        </div>
                        <a href="{{ route('seller.landing') }}" class="btn btn-accent">{{ __('customer.account.seller_cta_button') }}</a>
                    </div>
                @endif

                {{-- سه سفارش آخر --}}
                <section class="card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 font-bold text-gray-900">
                            @svg('heroicon-o-shopping-bag', 'size-5 text-gray-400') {{ __('customer.account.menu.orders') }}
                        </h2>
                        @if ($orders->isNotEmpty())
                            <a href="{{ route('account.orders') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('checkout.mine.view') }}</a>
                        @endif
                    </div>

                    @if ($orders->isEmpty())
                        <p class="mt-2 text-sm leading-7 text-gray-500">{{ __('checkout.mine.empty_text') }}</p>
                    @else
                        <ul class="mt-3 divide-y divide-gray-100 text-sm">
                            @foreach ($orders as $order)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                    <a href="{{ route('order.show', ['uuid' => $order->uuid]) }}" class="font-medium text-brand-700 hover:underline" dir="ltr">{{ $order->number }}</a>
                                    <span class="text-xs text-gray-500">{{ \App\Support\Jalali::date($order->placed_at) }}</span>
                                    <span class="text-gray-600">{{ __('checkout.status.'.$order->status) }}</span>
                                    <span class="font-bold text-gray-900">{{ \App\Support\Money::format($order->total) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- معلومات من --}}
                <section class="card p-5 md:p-6">
                    <h2 class="font-bold text-gray-900">{{ __('customer.account.profile') }}</h2>
                    <form method="post" action="{{ route('account.profile') }}" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate>
                        @csrf
                        @method('put')
                        <x-storefront.field name="name" :label="__('customer.account.name')" :value="$customer->name" required maxlength="100" autocomplete="name" />
                        <div>
                            <span class="form-label">{{ __('customer.account.phone') }}</span>
                            <p class="form-input flex items-center bg-gray-50 text-gray-500" dir="ltr">{{ $customer->phone }}</p>
                            <p class="form-help">{{ __('customer.account.phone_help') }}</p>
                        </div>
                        <x-storefront.field name="email" type="email" :label="__('customer.account.email')" :value="$customer->email" dir="ltr" autocomplete="email" maxlength="150" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary">{{ __('customer.account.save_profile') }}</button>
                        </div>
                    </form>
                </section>

                {{-- تغییر رمز --}}
                <section class="card p-5 md:p-6">
                    <h2 class="font-bold text-gray-900">{{ __('customer.account.password') }}</h2>
                    <form method="post" action="{{ route('account.password') }}" class="mt-4 grid gap-4 sm:grid-cols-3" novalidate>
                        @csrf
                        @method('put')
                        <x-storefront.field name="current_password" type="password" :label="__('customer.account.current_password')" required autocomplete="current-password" dir="ltr" />
                        <x-storefront.field name="password" type="password" :label="__('customer.account.new_password')" required autocomplete="new-password" dir="ltr" :help="__('customer.register.password_help')" />
                        <x-storefront.field name="password_confirmation" type="password" :label="__('customer.account.new_password_confirmation')" required autocomplete="new-password" dir="ltr" />
                        <div class="sm:col-span-3">
                            <button type="submit" class="btn btn-outline">{{ __('customer.account.save_password') }}</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
@endsection
