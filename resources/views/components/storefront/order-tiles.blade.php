{{--
    چهار خانه‌ی «سفارش‌های من» با شمار هر گروه (الگو: دیجی‌کالا، ۵ قوس ۱۴۰۵): جاری، تحویل‌شده، برگشت‌شده، لغوشده.
    هر خانه لیست سفارش‌های همان گروه را باز می‌کند. $active = گروه فعلی (در صفحه‌ی لیست).
--}}
@props(['counts', 'active' => null])
@php
    $tiles = [
        'current' => ['icon' => 'heroicon-s-truck', 'bg' => 'bg-sky-100 text-sky-600'],
        'delivered' => ['icon' => 'heroicon-s-check-badge', 'bg' => 'bg-green-100 text-green-600'],
        'returned' => ['icon' => 'heroicon-s-arrow-uturn-right', 'bg' => 'bg-amber-100 text-amber-600'],
        'cancelled' => ['icon' => 'heroicon-s-x-circle', 'bg' => 'bg-red-100 text-red-600'],
    ];
@endphp

<ul class="grid grid-cols-4 gap-1">
    @foreach ($tiles as $key => $tile)
        <li>
            <a href="{{ route('account.orders', ['status' => $key]) }}" @if ($active === $key) aria-current="page" @endif
               class="flex flex-col items-center gap-2 rounded-xl px-1 py-2 text-center {{ $active === $key ? 'bg-gray-100' : '' }}">
                <span class="relative">
                    <span class="flex size-14 items-center justify-center rounded-2xl {{ $tile['bg'] }}">
                        @svg($tile['icon'], 'size-8')
                    </span>
                    <span class="absolute -bottom-1 -start-1 flex min-w-6 items-center justify-center rounded-md bg-white px-1 text-xs font-bold text-gray-700 shadow ring-1 ring-gray-200">
                        {{ \App\Support\Num::toFa((string) ($counts[$key] ?? 0)) }}
                    </span>
                </span>
                <span class="text-xs font-medium text-gray-800">{{ __('customer.account.groups.'.$key) }}</span>
            </a>
        </li>
    @endforeach
</ul>
