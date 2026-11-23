@php
    use App\Support\Jalali;
    use App\Support\Money;
    use App\Support\Num;

    /** @var \App\Models\SellerSettlement $settlement */
    $store = app(\App\Services\StoreInfo::class);
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('commission.settlement_receipt.title') }} {{ Num::toFa((string) $settlement->number) }}</title>
    {{-- صفحه‌ی جدای چاپ؛ CSS درون صفحه و فونت از خود سرور (بی CDN) --}}
    <style>
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f3f4f6; color: #111827; margin: 0; padding: 1.5rem 1rem; line-height: 1.9; }
        .sheet { max-width: 34rem; margin: 0 auto; background: #fff; border-radius: .75rem; padding: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.1rem; margin: 0 0 .25rem; text-align: center; }
        h2 { font-size: .9rem; margin: 1rem 0 .25rem; }
        .store { text-align: center; color: #4b5563; font-size: .8rem; margin-bottom: .75rem; }
        .big { font-size: 1.3rem; font-weight: 700; text-align: center; margin: .5rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .info th { text-align: right; font-weight: 400; color: #6b7280; padding: .3rem 0; width: 40%; vertical-align: top; }
        .info td { text-align: left; padding: .3rem 0; font-weight: 700; }
        .total td { font-size: 1.1rem; color: #065f46; }
        .lines th, .lines td { border-bottom: 1px solid #e5e7eb; padding: .35rem .25rem; text-align: right; }
        .lines th { color: #6b7280; font-weight: 400; }
        .lines td.num { text-align: left; white-space: nowrap; }
        hr { border: 0; border-top: 1px dashed #d1d5db; margin: .75rem 0; }
        .note { font-size: .78rem; color: #4b5563; }
        .signs { display: flex; gap: 1rem; margin-top: 2rem; font-size: .8rem; color: #6b7280; }
        .signs div { flex: 1; border-top: 1px solid #9ca3af; padding-top: .3rem; text-align: center; }
        .print { display: block; width: 100%; margin: 1rem auto 0; max-width: 34rem; padding: .6rem; border: 0; border-radius: .5rem; background: #0e8c8c; color: #fff; font-family: inherit; font-size: .95rem; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; }
            .print { display: none; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <h1>{{ __('commission.settlement_receipt.title') }}</h1>
    <div class="store">{{ $store->name() }}</div>

    <div class="big">{{ Num::toFa((string) $settlement->number) }}</div>

    <hr>

    <table class="info">
        <tr>
            <th>{{ __('commission.settlement_receipt.seller') }}</th>
            <td>{{ $settlement->seller?->store_name ?? $settlement->seller?->full_name }}</td>
        </tr>
        <tr class="total">
            <th>{{ __('commission.settlement_receipt.amount') }}</th>
            <td>{{ Money::format($settlement->amount) }}</td>
        </tr>
        <tr>
            <th>{{ __('commission.settlement_receipt.method') }}</th>
            <td>{{ $settlement->methodLabel() }}</td>
        </tr>
        @if ($settlement->reference)
            <tr>
                <th>{{ __('commission.settlement_receipt.reference') }}</th>
                <td dir="ltr">{{ $settlement->reference }}</td>
            </tr>
        @endif
        <tr>
            <th>{{ __('commission.settlement_receipt.paid_at') }}</th>
            <td>{{ Jalali::date($settlement->paid_at) }}</td>
        </tr>
        <tr>
            <th>{{ __('commission.settlement_receipt.paid_by') }}</th>
            <td>{{ $settlement->creator?->name ?? '—' }}</td>
        </tr>
    </table>

    @if ($settlement->items->isNotEmpty())
        <h2>{{ __('commission.settlement_receipt.lines') }}</h2>
        <table class="lines">
            <tr>
                <th>{{ __('commission.settlement_receipt.line_ref') }}</th>
                <th>{{ __('commission.settlement_receipt.line_desc') }}</th>
                <th>{{ __('commission.settlement_receipt.line_amount') }}</th>
            </tr>
            @foreach ($settlement->items as $item)
                <tr>
                    <td dir="ltr">{{ Num::toFa((string) $item->transaction?->reference) }}</td>
                    <td>{{ $item->transaction?->description ?? $item->transaction?->typeLabel() }}</td>
                    <td class="num">{{ Money::format($item->amount) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($settlement->notes)
        <p class="note">{{ __('commission.settlement_receipt.notes') }}: {{ $settlement->notes }}</p>
    @endif

    <div class="signs">
        <div>{{ __('commission.settlement_receipt.sign_seller') }}</div>
        <div>{{ __('commission.settlement_receipt.sign_store') }}</div>
    </div>
</div>

<button type="button" class="print" onclick="window.print()">{{ __('commission.settlement_receipt.print') }}</button>
</body>
</html>
