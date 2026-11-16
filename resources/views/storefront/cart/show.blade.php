{{--
    سبد خرید: خط‌ها با قیمت و موجودی زنده، تغییر تعداد، حذف، خلاصه و کرایه‌ی تقریبی.
    مهمان هم می‌تواند ادامه بدهد (ثبت‌نام لازم نیست). همه‌چیز با فورم ساده؛ Alpine فقط تعداد را خودکار می‌فرستد.
--}}
@extends('storefront.layouts.app')

@section('title', __('cart.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@php
    use App\Support\Money;
    use App\Support\Num;
    $checkoutReady = Route::has('checkout.show');
    $kg = fn (int $grams): string => Num::format(bcdiv((string) $grams, '1000', 1), 1);
    $waText = $lines->isEmpty() ? null : $store->whatsappLink(
        __('cart.whatsapp_message')."\n".$lines->filter->counts()->map(fn ($l) => '• '.$l->product->tr('name').($l->variant->label ? ' ('.$l->variant->label.')' : '').' × '.Num::toFa((string) $l->qty))->implode("\n")
    );
@endphp

@section('content')
    <div class="container-page py-6 md:py-8">
        <h1 class="mb-5 text-xl font-bold text-gray-900 md:text-2xl">{{ __('cart.heading') }}</h1>
        <x-storefront.flash />

        @if ($lines->isEmpty())
            <div class="card p-8 text-center">
                @svg('heroicon-o-shopping-cart', 'mx-auto size-14 text-gray-300')
                <h2 class="mt-3 font-bold text-gray-900">{{ __('cart.empty_heading') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-gray-500">{{ __('cart.empty_text') }}</p>
                <a href="{{ route('home') }}" class="btn btn-primary mt-5">{{ __('cart.continue_shopping') }}</a>
            </div>
        @else
            @if ($totals['problems'] > 0)
                <p class="alert alert-warning mb-4" role="alert">{{ __('cart.problem_banner') }}</p>
            @endif

            <div class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
                {{-- خط‌ها --}}
                <ul class="space-y-3">
                    @foreach ($lines as $line)
                        <li class="card p-3 sm:p-4 {{ in_array($line->problem, ['unavailable', 'out_of_stock'], true) ? 'border-red-200 bg-red-50/40' : '' }}">
                            <div class="flex gap-3">
                                <a href="{{ $line->product->url() }}" class="size-20 shrink-0 overflow-hidden rounded-xl bg-white sm:size-24">
                                    @if ($line->image)
                                        <img src="{{ $line->image->url(300) }}" alt="{{ $line->image->altText($line->product->tr('name')) }}" width="96" height="96" loading="lazy" class="size-full object-contain">
                                    @else
                                        <span class="flex size-full items-center justify-center text-gray-300" aria-hidden="true">@svg('heroicon-o-photo', 'size-8')</span>
                                    @endif
                                </a>

                                <div class="min-w-0 flex-1">
                                    <a href="{{ $line->product->url() }}" class="line-clamp-2 text-sm leading-6 font-medium text-gray-900 hover:text-brand-700">{{ $line->product->tr('name') }}</a>
                                    @if ($line->variant->label)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $line->variant->label }}</p>
                                    @endif

                                    @if ($line->problem)
                                        <p class="mt-2 text-xs leading-6 {{ $line->problem === 'insufficient' ? 'text-accent-800' : 'text-red-700' }}" role="alert">
                                            {{ __('cart.problems.'.$line->problem, ['n' => Num::toFa((string) $line->maxQty)]) }}
                                        </p>
                                    @endif

                                    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                                        {{-- تعداد --}}
                                        <form method="post" action="{{ route('cart.update', ['item' => $line->item->id]) }}" class="flex items-center gap-2">
                                            @csrf @method('patch')
                                            <label class="sr-only" for="qty-{{ $line->item->id }}">{{ __('cart.qty') }}</label>
                                            <select id="qty-{{ $line->item->id }}" name="qty" class="form-input w-20 px-2" @change="$el.form.requestSubmit()" @disabled($line->maxQty < 1)>
                                                @for ($n = 1; $n <= max($line->maxQty, 1); $n++)
                                                    <option value="{{ $n }}" @selected(min($line->qty, max($line->maxQty, 1)) === $n)>{{ Num::toFa((string) $n) }}</option>
                                                @endfor
                                            </select>
                                            <noscript><button type="submit" class="btn btn-outline px-3">{{ __('cart.update') }}</button></noscript>
                                        </form>

                                        {{-- قیمت --}}
                                        <div class="text-end">
                                            @if ($line->price->hasDiscount())
                                                <p class="text-xs text-gray-400 line-through">{{ Money::format($line->price->comparePrice) }}</p>
                                            @endif
                                            <p class="text-sm font-bold {{ $line->counts() ? 'text-gray-900' : 'text-gray-400' }}">{{ Money::format($line->price->price) }}</p>
                                            @if ($line->qty > 1 && $line->counts())
                                                <p class="text-xs text-gray-500">{{ __('cart.line_total') }}: {{ Money::format($line->lineTotal) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="post" action="{{ route('cart.remove', ['item' => $line->item->id]) }}" class="mt-2 border-t border-gray-100 pt-2">
                                @csrf @method('delete')
                                <button type="submit" class="inline-flex min-h-11 items-center gap-1.5 text-sm text-red-700 hover:underline">
                                    @svg('heroicon-o-trash', 'size-4') {{ __('cart.remove') }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                {{-- خلاصه --}}
                <aside class="card p-5 lg:sticky lg:top-28" aria-label="{{ __('cart.summary') }}">
                    <h2 class="font-bold text-gray-900">{{ __('cart.summary') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('cart.subtotal', ['count' => Num::toFa((string) $totals['units'])]) }}</dt>
                            <dd class="font-medium text-gray-900">{{ Money::format($totals['subtotal']) }}</dd>
                        </div>
                        @if (Money::compare($totals['savings'], 0) > 0)
                            <div class="flex justify-between gap-3 text-red-700">
                                <dt>{{ __('cart.savings') }}</dt>
                                <dd class="font-medium">{{ Money::format($totals['savings']) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('cart.weight') }}</dt>
                            <dd class="text-gray-900">{{ __('cart.weight_kg', ['kg' => $kg($totals['weight'])]) }}</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">{{ __('cart.delivery') }}</dt>
                                <dd class="text-end font-medium text-gray-900">
                                    @if ($estimate === null)
                                        <span class="text-xs font-normal text-gray-500">{{ __('cart.delivery_none') }}</span>
                                    @elseif (isset($estimate['tooHeavy']))
                                        <span class="text-xs font-normal text-red-700">{{ __('cart.delivery_too_heavy') }}</span>
                                    @else
                                        {{ __('cart.delivery_range', ['min' => Num::format($estimate['min'], 0), 'max' => Num::format($estimate['max'], 0)]) }}
                                    @endif
                                </dd>
                            </div>
                            @if (is_array($estimate) && ! isset($estimate['tooHeavy']))
                                <p class="mt-1.5 text-xs leading-6 text-gray-500">{{ __('cart.delivery_note') }}</p>
                            @endif
                            @if ($store->pickupEnabled())
                                <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-brand-50 p-2.5 text-xs leading-6 text-brand-900">
                                    @svg('heroicon-o-building-storefront', 'mt-0.5 size-4 shrink-0')
                                    <span><strong>{{ __('cart.pickup_free') }}</strong> {{ __('cart.pickup_at', ['address' => $store->pickupAddress()]) }}</span>
                                </p>
                            @endif
                        </div>
                    </dl>

                    @if ($checkoutReady)
                        <a href="{{ route('checkout.show') }}" class="btn btn-primary mt-5 w-full" @if ($totals['units'] === 0) aria-disabled="true" @endif>{{ __('cart.checkout') }}</a>
                        @guest('customer')
                            <p class="mt-2 text-center text-xs leading-6 text-gray-500">{{ __('cart.guest_note') }}</p>
                        @endguest
                    @else
                        <button type="button" class="btn btn-primary mt-5 w-full" disabled aria-disabled="true">{{ __('cart.checkout_soon') }}</button>
                    @endif

                    @if ($waText)
                        <a href="{{ $waText }}" target="_blank" rel="noopener" class="btn btn-whatsapp mt-2 w-full">
                            @svg('heroicon-o-chat-bubble-left-right', 'size-5') {{ __('cart.whatsapp') }}
                        </a>
                    @endif
                    <a href="{{ route('home') }}" class="btn btn-outline mt-2 w-full">{{ __('cart.continue_shopping') }}</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
