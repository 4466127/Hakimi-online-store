{{-- کارت «قدم‌های راه‌اندازی» در داشبورد پنل مدیریت. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ $storeName }}
        </x-slot>

        <x-slot name="description">
            {{ $today }}
            —
            {{ $isOpen ? __('admin.start.store_open') : __('admin.start.store_closed') }}
        </x-slot>

        @if (count($steps) > 0)
            <p style="margin-bottom:.75rem; font-weight:600;">{{ __('admin.start.heading') }}</p>

            <ul style="display:flex; flex-direction:column; gap:.5rem; padding:0; margin:0; list-style:none;">
                @foreach ($steps as $step)
                    <li style="display:flex; align-items:center; gap:.5rem;">
                        <span aria-hidden="true" style="font-size:1.1rem; color: {{ $step['done'] ? '#059669' : '#d97706' }};">
                            {{ $step['done'] ? '✔' : '○' }}
                        </span>
                        @if ($step['done'])
                            <span style="opacity:.7;">{{ $step['label'] }}</span>
                        @else
                            <x-filament::link :href="$step['url']">{{ $step['label'] }}</x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p>{{ __('admin.start.nothing') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
