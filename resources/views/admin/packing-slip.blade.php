@php
    use App\Support\Jalali;
    use App\Support\Money;
    use App\Support\Num;
    use App\Support\Phone;

    /** @var \App\Models\Order $order */
    /** @var \Illuminate\Support\Collection $packages */
    $count = $packages->count();
    $day = $order->delivery_date?->format('Y-m-d');
    $place = collect([$order->province?->tr('name'), $order->city, $order->district])
        ->filter(fn ($part) => is_string($part) && trim($part) !== '')
        ->unique()
        ->implode('، ');
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('admin.packing.title') }} {{ Num::toFa((string) $order->number) }}</title>
    {{--
        برگه‌ی بسته‌بندی: صفحه‌ی جدای چاپ، CSS درون خود صفحه (بی CDN).
        هر بسته یک برگه‌ی ۱۰ سانتی (اندازه‌ی برچسب ۱۰×۱۵)؛ روی کاغذ A4 هم چاپ می‌شود و با قیچی بریده می‌شود.
    --}}
    <style>
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-display: swap; }
        * { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f3f4f6; color: #111827; margin: 0; padding: 1.25rem; line-height: 1.7; }
        .slip { width: 100mm; min-height: 140mm; margin: 0 auto 1.25rem; background: #fff; border: 1.5px dashed #9ca3af; padding: 4mm; }
        .head { display: flex; align-items: center; justify-content: space-between; gap: 3mm; }
        .head img { height: 9mm; width: auto; }
        .num { text-align: left; }
        .num b { display: block; font-size: 15pt; letter-spacing: .04em; }
        .num span { font-size: 8pt; color: #4b5563; }
        .box { border-top: 1px solid #111827; margin-top: 2.5mm; padding-top: 2mm; }
        .label { font-size: 8pt; color: #4b5563; margin: 0; }
        .sender { font-size: 9pt; margin: 0; }
        .to-name { font-size: 15pt; font-weight: 700; margin: 0; line-height: 1.4; }
        .to-phone { font-size: 14pt; font-weight: 700; margin: 0; direction: ltr; text-align: right; }
        .addr { font-size: 11pt; margin: 1mm 0 0; }
        .small { font-size: 8.5pt; margin: 1mm 0 0; color: #374151; }
        .cash { margin-top: 2.5mm; border: 2px solid #111827; border-radius: 2mm; padding: 2mm 3mm; text-align: center; }
        .cash b { display: block; font-size: 16pt; }
        .cash span { font-size: 8.5pt; }
        .paid { border-style: dashed; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 1mm; }
        th { font-weight: 400; color: #4b5563; text-align: right; border-bottom: 1px solid #d1d5db; padding: .6mm 0; }
        td { border-bottom: 1px dotted #d1d5db; padding: .8mm 0; vertical-align: top; }
        td.q, th.q { text-align: center; width: 12mm; font-weight: 700; }
        .sku { color: #6b7280; font-size: 7.5pt; direction: ltr; display: inline-block; }
        .foot { margin-top: 2.5mm; font-size: 7.5pt; color: #6b7280; }
        .tools { max-width: 100mm; margin: 0 auto 1rem; }
        .print { display: block; width: 100%; padding: .6rem; border: 0; border-radius: .5rem; background: #0e8c8c; color: #fff; font-family: inherit; font-size: .95rem; cursor: pointer; }
        .tip { font-size: .75rem; color: #4b5563; margin: .5rem 0 0; }
        .empty { max-width: 100mm; margin: 2rem auto; background: #fff; padding: 1rem; border-radius: .5rem; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .tools { display: none; }
            .slip { margin: 0 0 4mm; break-after: page; page-break-after: always; }
            .slip:last-of-type { break-after: auto; page-break-after: auto; }
        }
    </style>
</head>
<body>
@if ($count === 0)
    <p class="empty">{{ __('admin.packing.nothing') }}</p>
@else
    <div class="tools">
        <button type="button" class="print" onclick="window.print()">{{ __('admin.packing.print') }}</button>
        <p class="tip">{{ __('admin.packing.tip', ['count' => Num::toFa((string) $count)]) }}</p>
    </div>

    @foreach ($packages as $i => $package)
        <section class="slip">
            <div class="head">
                <img src="{{ $store->logoUrl() }}" alt="{{ $store->name() }}">
                <div class="num">
                    <span>{{ __('admin.packing.order') }}</span>
                    <b>{{ Num::toFa((string) $order->number) }}</b>
                    <span>{{ __('admin.packing.package_of', ['n' => Num::toFa((string) ($i + 1)), 'count' => Num::toFa((string) $count)]) }}</span>
                </div>
            </div>

            {{-- فرستنده: فروشنده‌ی این بسته --}}
            <div class="box">
                <p class="label">{{ __('admin.packing.sender') }}</p>
                <p class="sender">
                    <b>{{ $package['sender']['name'] }}</b>
                    @if ($package['sender']['phone'])
                        — <span dir="ltr">{{ $package['sender']['phone'] }}</span>
                    @endif
                </p>
                @if ($package['sender']['address'])
                    <p class="small">{{ $package['sender']['address'] }}</p>
                @endif
            </div>

            {{-- گیرنده --}}
            <div class="box">
                <p class="label">{{ __('admin.packing.recipient') }}</p>
                <p class="to-name">{{ $order->recipient_name }}</p>
                <p class="to-phone">{{ Phone::display($order->recipient_phone) }}</p>

                @if ($order->isPickup())
                    <p class="addr"><b>{{ __('admin.packing.pickup') }}</b>
                        @if ($order->collectionPoint)
                            {{ $order->collectionPoint->tr('name') }}
                        @endif
                    </p>
                    @if ($order->collectionPoint?->displayAddress())
                        <p class="small">{{ $order->collectionPoint->displayAddress() }}</p>
                    @endif
                @else
                    <p class="addr">
                        @if ($place !== '')<b>{{ $place }}</b> — @endif{{ $order->address_line }}
                    </p>
                    @if ($order->delivery_instructions)
                        <p class="small">{{ __('admin.packing.instructions') }}: {{ $order->delivery_instructions }}</p>
                    @endif
                    @if ($order->hasLocation())
                        <p class="small">{{ __('admin.packing.has_location') }}</p>
                    @endif
                @endif

                @if ($day)
                    <p class="small"><b>{{ __('admin.packing.day') }}:</b> {{ Jalali::weekday($day) }}، {{ Jalali::formatLong($day) }}</p>
                @endif
            </div>

            {{-- پول: چقدر گرفته شود (برای کل سفارش؛ چند بسته با هم یک بار پول می‌گیرند) --}}
            @if ($due !== null)
                <div class="cash">
                    <span>{{ __('admin.packing.collect') }}</span>
                    <b>{{ Money::format($due) }}</b>
                    <span>{{ $paymentName }}@if ($count > 1) — {{ __('admin.packing.collect_whole') }}@endif</span>
                </div>
            @else
                <div class="cash paid">
                    <b>{{ __('admin.packing.paid') }}</b>
                    <span>{{ __('admin.packing.paid_note') }}</span>
                </div>
            @endif

            {{-- اجناس این بسته --}}
            <div class="box">
                <p class="label">{{ __('admin.packing.items') }}</p>
                <table>
                    <thead>
                        <tr><th>{{ __('admin.packing.item') }}</th><th class="q">{{ __('admin.packing.qty') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($package['items'] as $item)
                            <tr>
                                <td>
                                    {{ $item->product_name }}
                                    @if ($item->variant_label) — {{ $item->variant_label }} @endif
                                    @if ($item->sku)<br><span class="sku">{{ $item->sku }}</span>@endif
                                </td>
                                <td class="q">{{ Num::toFa((string) $item->activeQty()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="small"><b>{{ __('admin.packing.total_qty') }}:</b> {{ Num::toFa((string) $package['items']->sum(fn ($item) => $item->activeQty())) }}</p>
            </div>

            @if ($order->customer_note)
                <div class="box">
                    <p class="label">{{ __('admin.packing.customer_note') }}</p>
                    <p class="small">{{ $order->customer_note }}</p>
                </div>
            @endif

            <p class="foot">
                {{ Num::toFa((string) $package['number']) }} · {{ __('admin.packing.printed', ['date' => Jalali::dateTime(now())]) }}<br>
                {{ __('admin.packing.code_note') }}
            </p>
        </section>
    @endforeach
@endif
</body>
</html>
