{{-- «آدرس‌های من»: آدرس‌هایی که بعد از سفارش ذخیره شده؛ مشتری می‌تواند پاک کند. --}}
@extends('storefront.layouts.app')

@section('title', __('customer.addresses.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    <div class="container-page py-6 md:py-10">
        <div class="mx-auto max-w-3xl">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ __('customer.addresses.title') }}</h1>
                <a href="{{ route('account') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('customer.account.title') }}</a>
            </div>

            <x-storefront.flash />

            <p class="form-help mb-4">{{ __('customer.addresses.help', ['max' => \App\Support\Num::toFa((string) $max)]) }}</p>

            @if ($addresses->isEmpty())
                <div class="card flex flex-col items-center gap-3 p-8 text-center">
                    @svg('heroicon-o-map-pin', 'size-10 text-gray-300')
                    <p class="text-gray-700">{{ __('customer.addresses.empty') }}</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($addresses as $address)
                        <li class="card flex items-start gap-3 p-4">
                            @svg('heroicon-o-map-pin', 'mt-0.5 size-6 shrink-0 text-brand-600')
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-medium text-gray-900">
                                    {{ $address->recipient_name }}
                                    <span class="font-normal text-gray-500" dir="ltr">{{ \App\Support\Phone::display($address->recipient_phone) }}</span>
                                </p>
                                <p class="mt-1 leading-6 text-gray-700">{{ $address->summary() }}</p>
                                @if ($address->delivery_instructions)
                                    <p class="mt-1 text-xs text-gray-500">{{ $address->delivery_instructions }}</p>
                                @endif
                                @if ($address->delivery_lat !== null)
                                    <p class="mt-1 flex items-center gap-1 text-xs text-green-700">
                                        @svg('heroicon-o-check-circle', 'size-4') {{ __('customer.addresses.has_location') }}
                                    </p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('customer.addresses.last_used', ['date' => \App\Support\Jalali::date($address->last_used_at)]) }}
                                </p>
                            </div>
                            <form method="post" action="{{ route('account.addresses.destroy', ['address' => $address->id]) }}"
                                  x-data x-on:submit="if (! confirm(@js(__('customer.addresses.confirm_delete')))) $event.preventDefault()">
                                @csrf
                                @method('delete')
                                <button type="submit" class="btn min-h-11 px-3 text-sm text-red-700 hover:bg-red-50">
                                    @svg('heroicon-o-trash', 'size-5')
                                    <span>{{ __('customer.addresses.delete') }}</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
