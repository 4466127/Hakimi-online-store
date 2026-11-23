{{-- «سفارش‌ها به منبع تبلیغ» در داشبورد (مرحله‌ی ۹-۲). --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('admin.campaigns.widget_heading')" :description="__('admin.campaigns.widget_help')">
        @if (count($rows) === 0)
            <p style="opacity:.7;">{{ __('admin.campaigns.widget_empty') }}</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
                    <thead>
                        <tr style="text-align:start; border-bottom:1px solid rgba(128,128,128,.3);">
                            <th style="padding:.5rem; text-align:start;">{{ __('admin.campaigns.source') }}</th>
                            <th style="padding:.5rem; text-align:start;">{{ __('admin.campaigns.recent_count') }}</th>
                            <th style="padding:.5rem; text-align:start;">{{ __('admin.campaigns.recent_total') }}</th>
                            <th style="padding:.5rem; text-align:start;">{{ __('admin.campaigns.all_count') }}</th>
                            <th style="padding:.5rem; text-align:start;">{{ __('admin.campaigns.all_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                                <td style="padding:.5rem; font-weight:600;" dir="auto">{{ $row['source'] }}</td>
                                <td style="padding:.5rem;">{{ $row['recent_count'] }}</td>
                                <td style="padding:.5rem;">{{ $row['recent_total'] }}</td>
                                <td style="padding:.5rem;">{{ $row['all_count'] }}</td>
                                <td style="padding:.5rem;">{{ $row['all_total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
