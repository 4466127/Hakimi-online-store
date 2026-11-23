@php
    use App\Support\Jalali;
    use App\Support\Money;
    use App\Support\Num;
    use App\Support\Phone;

    /** @var \App\Models\Payment $payment */
    /** @var \App\Models\Order|null $order */
    $store = app(\App\Services\StoreInfo::class);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('payments.receipt.title') }} {{ Num::toFa((string) $payment->receipt_number) }}</title>
    {{-- رسید صفحه‌ی جدای چاپ است؛ CSS کوچک و درون خود صفحه (بی CDN، مثل بقیه‌ی پروژه) --}}
    <style>
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f3f4f6; color: #111827; margin: 0; padding: 1.5rem; line-height: 1.9; }
        .sheet { max-width: 20rem; margin: 0 auto; background: #fff; border-radius: .75rem; padding: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.05rem; margin: 0 0 .25rem; text-align: center; }
        .store { text-align: center; color: #4b5563; font-size: .8rem; margin-bottom: .75rem; }
        .big { font-size: 1.35rem; font-weight: 700; text-align: center; margin: .5rem 0; letter-spacing: .05em; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th { text-align: right; font-weight: 400; color: #6b7280; padding: .3rem 0; vertical-align: top; width: 45%; }
        td { text-align: left; padding: .3rem 0; font-weight: 700; }
        .total td { font-size: 1.05rem; color: #065f46; }
        hr { border: 0; border-top: 1px dashed #d1d5db; margin: .75rem 0; }
        .note { font-size: .78rem; color: #4b5563; }
        .ok { color: #047857; }
        .sign { margin-top: 1.25rem; font-size: .78rem; color: #6b7280; }
        .print { display: block; width: 100%; margin: 1rem auto 0; max-width: 20rem; padding: .6rem; border: 0; border-radius: .5rem; background: #0e8c8c; color: #fff; font-family: inherit; font-size: .95rem; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; }
            .print { display: none; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <h1>{{ __('payments.receipt.title') }}</h1>
    <div class="store">
        {{ $store->name() }}
        @if ($store->phoneDisplay())
            — {{ __('payments.receipt.phone') }}: {{ $store->phoneDisplay() }}
        @endif
    </div>

    <div class="big">{{ Num::toFa((string) $payment->receipt_number) }}</div>

    <hr>

    <table>
        <tr>
            <th>{{ __('payments.receipt.order_number') }}</th>
            <td>{{ Num::toFa((string) ($order?->number ?? '—')) }}</td>
        </tr>
        <tr>
            <th>{{ __('payments.receipt.date') }}</th>
            <td>{{ Jalali::dateTime($payment->collected_at ?? $payment->paid_at) }}</td>
        </tr>
        <tr>
            <th>{{ __('payments.receipt.purchaser') }}</th>
            <td>{{ $order?->purchaser_name }}</td>
        </tr>
        @if ($payment->payer_name)
            <tr>
                <th>{{ __('payments.receipt.payer') }}</th>
                <td>{{ $payment->payer_name }}</td>
            </tr>
        @endif
        @if ($order)
            <tr>
                <th>{{ __('payments.receipt.order_total') }}</th>
                <td>{{ Money::format($order->total) }}</td>
            </tr>
        @endif
        <tr class="total">
            <th>{{ __('payments.receipt.amount') }}</th>
            <td>{{ Money::format($payment->amount_received ?? $payment->amount) }}</td>
        </tr>
        <tr>
            <th>{{ __('payments.receipt.place') }}</th>
            <td>
                {{ $payment->collected_at_type ? __('payments.place.'.$payment->collected_at_type) : '—' }}
                @if ($payment->collectionPoint)
                    — {{ $payment->collectionPoint->tr('name') }}
                @endif
            </td>
        </tr>
        <tr>
            <th>{{ __('payments.receipt.collector') }}</th>
            <td>{{ $payment->collector?->name ?? '—' }}</td>
        </tr>
        @if ($payment->note)
            <tr>
                <th>{{ __('payments.receipt.note') }}</th>
                <td>{{ $payment->note }}</td>
            </tr>
        @endif
    </table>

    <hr>

    <p class="note {{ $payment->delivery_code_verified ? 'ok' : '' }}">
        {{ __($payment->delivery_code_verified ? 'payments.receipt.code_verified' : 'payments.receipt.code_not_verified') }}
    </p>
    <p class="note">{{ __('payments.receipt.keep') }}</p>
    <p class="note">{{ __('payments.receipt.thanks') }}</p>

    @if ($order)
        <p class="sign">{{ __('payments.receipt.purchaser_phone') }}: {{ Phone::display($order->purchaser_phone) }}</p>
    @endif
</div>

<button type="button" class="print" onclick="window.print()">{{ __('payments.receipt.print') }}</button>
</body>
</html>
