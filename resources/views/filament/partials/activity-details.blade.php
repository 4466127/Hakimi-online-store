{{-- جزئیات یک سطر تاریخچه‌ی فعالیت: هر چیز که عوض شد، قبل و بعد. --}}
@php
    $show = function (mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return (string) __($value ? 'admin.yes' : 'admin.no');
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    };
@endphp

<table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
    <thead>
        <tr style="text-align:start; border-bottom:1px solid rgba(128,128,128,.3);">
            <th style="padding:.5rem; text-align:start;">{{ __('admin.activity.field') }}</th>
            <th style="padding:.5rem; text-align:start;">{{ __('admin.activity.old') }}</th>
            <th style="padding:.5rem; text-align:start;">{{ __('admin.activity.new') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($log->changes ?? []) as $field => $change)
            @php
                $fieldLabel = ((array) __('admin.activity.fields'))[$field] ?? $field;
                $isPair = is_array($change) && (array_key_exists('old', $change) || array_key_exists('new', $change));
            @endphp
            <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                <td style="padding:.5rem; font-weight:600;">{{ $fieldLabel }}</td>
                @if ($isPair)
                    <td style="padding:.5rem; word-break:break-word;">{{ $show($change['old'] ?? null) }}</td>
                    <td style="padding:.5rem; word-break:break-word;">{{ $show($change['new'] ?? null) }}</td>
                @else
                    <td style="padding:.5rem;" colspan="2">{{ $show($change) }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
