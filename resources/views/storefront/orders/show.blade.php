{{--
    صفحه‌ی سفارش مشتری (آدرس با uuid؛ حدس‌زدنی نیست).
    بعد از ثبت سفارش «تشکر» بالای صفحه می‌آید. هیچ معلومات فروشنده یا کمیسیون اینجا نیست.
--}}
@extends('storefront.layouts.app')

@section('title', __('checkout.order.title', ['number' => $order->number]))
@push('head')<meta name="robots" content="noindex">@endpush

@php
    use App\Support\Jalali;
    use App\Support\Money;
    use App\Support\Num;
    use App\Support\Phone;

    $statusColors = [
        'pending' => 'bg-accent-100 text-accent-900',
        'confirmed' => 'bg-brand-100 text-brand-900',
        'processing' => 'bg-brand-100 text-brand-900',
        'shipped' => 'bg-brand-100 text-brand-900',
        'delivered' => 'bg-green-100 text-green-900',
        'cancelled' => 'bg-red-100 text-red-900',
        'returned' => 'bg-gray-100 text-gray-800',
    ];
    $waLink = $store->whatsappLink(__('checkout.order.whatsapp_message', ['number' => $order->number]));
@endphp

@section('content')
    <div class="container-page py-6 md:py-8">
        @if ($justPlaced)
            <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 p-5 text-center" role="status">
                @svg('heroicon-o-check-circle', 'mx-auto size-12 text-green-600')
                <h1 class="mt-2 text-lg font-bold text-green-900 md:text-xl">{{ __('checkout.order.thanks_heading') }}</h1>
                <p class="mt-2 text-sm leading-7 text-green-900">{{ __('checkout.order.thanks_text', ['number' => $order->number]) }}</p>
                <p class="mt-2 text-xs leading-6 text-green-800">{{ __('checkout.order.save_link') }}</p>
            </div>
        @else
            <h1 class="mb-5 text-xl font-bold text-gray-900 md:text-2xl">{{ __('checkout.order.heading', ['number' => $order->number]) }}</h1>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
            <div class="min-w-0 space-y-5">
                {{-- وضعیت --}}
                <section class="card p-5">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('checkout.order.number') }}</dt>
                            <dd class="mt-0.5 font-bold text-gray-900" dir="ltr">{{ $order->number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('checkout.order.placed_at') }}</dt>
                            <dd class="mt-0.5 text-sm text-gray-900">{{ Jalali::dateTime($order->placed_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('checkout.order.status') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ __('checkout.status.'.$order->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('checkout.order.payment_status') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ __('checkout.payment_status.'.$order->payment_status) }}</dd>
                        </div>
                    </dl>

                    {{-- پول گرفته شد: رسید مشتری (مرحله‌ی ۳) --}}
                    @if ($paid)
                        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 p-4">
                            <p class="text-sm font-bold text-green-900">{{ __('checkout.order.paid_heading') }}</p>
                            <p class="mt-1.5 text-xs leading-6 text-green-900">
                                {{ __('checkout.order.paid_body', [
                                    'amount' => Money::format($paid->amount_received ?? $paid->amount),
                                    'receipt' => Num::toFa((string) $paid->receipt_number),
                                ]) }}
                            </p>
                        </div>
                    @endif

                    @if ($order->delivery_code && $order->isOpen() && ! $paid)
                        <div class="mt-4 rounded-xl border border-brand-200 bg-brand-50 p-4">
                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="text-sm font-bold text-brand-900">{{ __('checkout.order.code_heading') }}:</span>
                                <span class="text-2xl font-bold tracking-widest text-brand-900" dir="ltr">{{ Num::toFa($order->delivery_code) }}</span>
                            </p>
                            <p class="mt-1.5 text-xs leading-6 text-brand-900">{{ __('checkout.order.code_help') }}</p>
                        </div>
                    @endif

                    @if ($order->needs_stock_check && $order->isOpen())
                        <p class="alert alert-warning mt-4" role="status">{{ __('checkout.order.stock_check') }}</p>
                    @endif
                    @if ($order->status === 'cancelled' && $order->cancel_reason)
                        <p class="alert alert-error mt-4" role="status">
                            {{ __('checkout.order.cancel_reason') }}: {{ $order->cancel_reason }}
                        </p>
                    @endif
                </section>

                {{-- اجناس --}}
                <section class="card p-5">
                    <h2 class="font-bold text-gray-900">{{ __('checkout.order.items') }}</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($order->items as $item)
                            <li class="flex items-start gap-3 border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                <span class="size-14 shrink-0 overflow-hidden rounded-lg bg-white">
                                    @if ($item->image_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path) }}"
                                             alt="{{ $item->product_name }}" width="56" height="56" loading="lazy" class="size-full object-contain">
                                    @else
                                        <span class="flex size-full items-center justify-center text-gray-300" aria-hidden="true">@svg('heroicon-o-photo', 'size-6')</span>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1 text-sm">
                                    <span class="block leading-6 text-gray-900">{{ $item->product_name }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">
                                        {{ $item->variant_label ? $item->variant_label.' • ' : '' }}{{ __('checkout.order.qty', ['n' => Num::toFa((string) $item->qty)]) }}
                                        • {{ __('checkout.order.unit_price') }}: {{ Money::format($item->unit_price) }}
                                    </span>
                                </span>
                                <span class="text-sm font-bold whitespace-nowrap text-gray-900">{{ Money::format($item->line_total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- تحویل و پرداخت --}}
                <section class="card p-5">
                    <h2 class="font-bold text-gray-900">{{ __('checkout.order.delivery_heading') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">{{ __('checkout.order.delivery_method') }}:</dt>
                            <dd class="text-gray-900">
                                {{ $order->isPickup() ? __('checkout.delivery.pickup') : __('checkout.delivery.home') }}
                            </dd>
                        </div>

                        @if ($order->isPickup())
                            @if ($order->delivery_date)
                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="text-gray-500">{{ __('checkout.order.pickup_day') }}:</dt>
                                    <dd class="text-gray-900">
                                        {{ Jalali::weekday($order->delivery_date->format('Y-m-d')) }}، {{ Jalali::formatLong($order->delivery_date->format('Y-m-d')) }}
                                    </dd>
                                </div>
                            @endif
                            @if ($order->collectionPoint)
                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="text-gray-500">{{ __('checkout.order.pickup_point') }}:</dt>
                                    <dd class="text-gray-900">
                                        {{ $order->collectionPoint->tr('name') }}
                                        @if ($order->collectionPoint->displayAddress())
                                            — {{ $order->collectionPoint->displayAddress() }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        @else
                            @if ($order->delivery_date)
                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="text-gray-500">{{ __('checkout.order.delivery_day') }}:</dt>
                                    <dd class="text-gray-900">
                                        {{ Jalali::weekday($order->delivery_date->format('Y-m-d')) }}، {{ Jalali::formatLong($order->delivery_date->format('Y-m-d')) }}
                                    </dd>
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-gray-500">{{ __('checkout.order.address') }}:</dt>
                                <dd class="text-gray-900">
                                    {{ collect([$order->province?->tr('name'), $order->city, $order->district, $order->address_line])->filter()->implode('، ') }}
                                </dd>
                            </div>
                            @if ($order->delivery_instructions)
                                <div class="flex flex-wrap gap-x-2">
                                    <dt class="text-gray-500">{{ __('checkout.order.instructions') }}:</dt>
                                    <dd class="text-gray-900">{{ $order->delivery_instructions }}</dd>
                                </div>
                            @endif
                        @endif

                        <div class="flex flex-wrap gap-x-2">
                            <dt class="text-gray-500">{{ __('checkout.order.recipient') }}:</dt>
                            <dd class="text-gray-900">{{ $order->recipient_name }} — <span dir="ltr">{{ Phone::display($order->recipient_phone) }}</span></dd>
                        </div>
                        @if ($order->is_gift)
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-gray-500">{{ __('checkout.order.purchaser') }}:</dt>
                                <dd class="text-gray-900">{{ $order->purchaser_name }} — <span dir="ltr">{{ Phone::display($order->purchaser_phone) }}</span></dd>
                            </div>
                        @endif
                        @if ($order->customer_note)
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-gray-500">{{ __('checkout.order.note') }}:</dt>
                                <dd class="text-gray-900">{{ $order->customer_note }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <p class="text-sm">
                            <span class="text-gray-500">{{ __('checkout.order.payment_method') }}:</span>
                            <span class="font-medium text-gray-900">{{ $method?->tr('name') ?? $order->payment_method_code }}</span>
                        </p>
                        @if ($method?->tr('instructions'))
                            <p class="mt-2 rounded-lg bg-brand-50 p-3 text-xs leading-6 text-brand-900">{{ $method->tr('instructions') }}</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- مبلغ‌ها و کمک --}}
            <aside class="space-y-4 lg:sticky lg:top-28">
                <div class="card p-5">
                    <h2 class="font-bold text-gray-900">{{ __('checkout.totals.heading') }}</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('checkout.totals.items', ['count' => Num::toFa((string) $order->items->sum('qty'))]) }}</dt>
                            <dd class="font-medium text-gray-900">{{ Money::format($order->subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('checkout.totals.delivery') }}</dt>
                            <dd class="font-medium text-gray-900">
                                {{ Money::compare($order->delivery_fee, 0) > 0 ? Money::format($order->delivery_fee) : __('checkout.totals.free') }}
                            </dd>
                        </div>
                        @if (Money::compare($order->payment_fee, 0) > 0)
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">{{ __('checkout.totals.payment_fee') }}</dt>
                                <dd class="font-medium text-gray-900">{{ Money::format($order->payment_fee) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3 border-t border-gray-100 pt-3 text-base">
                            <dt class="font-bold text-gray-900">{{ __('checkout.totals.total') }}</dt>
                            <dd class="font-bold text-brand-800">{{ Money::format($order->total) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card p-5">
                    <h2 class="font-bold text-gray-900">{{ __('checkout.order.help_heading') }}</h2>
                    <div class="mt-3 space-y-2">
                        @if ($waLink)
                            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn btn-whatsapp w-full">
                                @svg('heroicon-o-chat-bubble-left-right', 'size-5') {{ __('checkout.order.whatsapp') }}
                            </a>
                        @endif
                        @if ($store->telLink())
                            <a href="{{ $store->telLink() }}" class="btn btn-outline w-full">
                                @svg('heroicon-o-phone', 'size-5') {{ __('checkout.order.call') }}
                            </a>
                        @endif
                        @auth('customer')
                            <a href="{{ route('account.orders') }}" class="btn btn-outline w-full">{{ __('checkout.order.my_orders') }}</a>
                        @endauth
                        <a href="{{ route('order.track') }}" class="btn btn-outline w-full">
                            @svg('heroicon-o-magnifying-glass', 'size-5') {{ __('checkout.order.track') }}
                        </a>
                        <a href="{{ route('home') }}" class="btn btn-primary w-full">{{ __('checkout.order.back_home') }}</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
