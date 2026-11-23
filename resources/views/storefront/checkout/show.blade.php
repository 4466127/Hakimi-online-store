{{--
    صفحه‌ی تسویه: معلومات خریدار و گیرنده، روش تحویل (درِ خانه با انتخاب روز / حضوری در نمایندگی)،
    آدرس، روش پرداخت و خلاصه‌ی مبلغ‌ها.

    قاعده‌ی مهم: **هیچ محاسبه‌ی پولی در مرورگر نیست.** همه‌ی مبلغ‌ها (کرایه‌ی هر روز، کارمزد، جمع) در
    سرور حساب و فرمت شده و در `$calc` آماده است؛ Alpine فقط متن درست را نشان می‌دهد. ثبت سفارش هم
    همه‌چیز را از نو زیر قفل حساب می‌کند.
--}}
@extends('storefront.layouts.app')

@section('title', __('checkout.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@php
    use App\Support\Money;
    use App\Support\Num;

    $subtotal = $totals['subtotal'];
    // کد تخفیف (مرحله‌ی ۹-۳): مبلغ را سرور حساب کرده؛ فقط روی قیمت اجناس، نه کرایه
    $discountAmount = $discount['amount'] ?? '0.00';
    $goods = Money::sub($subtotal, $discountAmount);
    $kg = Num::format(bcdiv((string) $totals['weight'], '1000', 1), 1);

    // جمع‌های آماده‌ی سرور: کلید = «کد پرداخت|pickup|تاریخ» یا «کد پرداخت|شناسه‌ی ولایت|تاریخ»
    $calc = [];
    foreach ($methods as $row) {
        $code = $row['method']->code;
        $paymentFee = Money::of($row['method']->fee);
        $feeText = Money::compare($paymentFee, 0) > 0 ? Money::format($paymentFee) : null;

        if ($row['pickup'] && $pickupEnabled) {
            foreach ($pickupDays as $option) {
                $calc[$code.'|pickup|'.$option->dateString()] = [
                    'delivery' => __('checkout.totals.free'),
                    'fee' => $feeText,
                    'total' => Money::format(Money::add($goods, $paymentFee)),
                ];
            }
        }
        if ($row['delivery']) {
            foreach ($days as $provinceId => $options) {
                foreach ($options as $option) {
                    $calc[$code.'|'.$provinceId.'|'.$option->dateString()] = [
                        'delivery' => Money::format($option->total),
                        'fee' => $feeText,
                        'total' => Money::format(Money::add(Money::add($goods, $option->total), $paymentFee)),
                    ];
                }
            }
        }
    }

    // روزهای هر حالت (برای اینکه با عوض شدن روش تحویل یا ولایت، روزِ انتخاب‌شده خودکار درست شود)
    $dayLists = ['pickup' => array_map(fn ($o) => $o->dateString(), $pickupDays)];
    foreach ($days as $provinceId => $options) {
        $dayLists['delivery|'.$provinceId] = array_map(fn ($o) => $o->dateString(), $options);
    }

    // کدام روش پرداخت با کدام روش تحویل می‌خواند (برای عوض شدن خودکار انتخاب)
    $fits = [
        'delivery' => $methods->filter(fn ($r) => $r['delivery'])->map(fn ($r) => $r['method']->code)->values()->all(),
        'pickup' => $methods->filter(fn ($r) => $r['pickup'])->map(fn ($r) => $r['method']->code)->values()->all(),
    ];

    $method = old('delivery_method', $defaults['delivery_method']);
    $provinceId = (int) old('province_id', $defaults['province_id'] ?? 0);
    $payment = old('payment_method_code', $fits[$method][0] ?? '');
    $provinceNames = implode('، ', \App\Models\Province::deliverableNames());
@endphp

@section('content')
    <div class="container-page py-6 md:py-8">
        <h1 class="mb-5 text-xl font-bold text-gray-900 md:text-2xl">{{ __('checkout.heading') }}</h1>

        <x-storefront.flash />
        <x-storefront.form-errors />

        <form method="post" action="{{ route('checkout.place') }}" novalidate
              x-data="{
                  method: @js($method),
                  province: @js((string) $provinceId),
                  gift: @js((bool) old('is_gift', false)),
                  day: @js(old('delivery_date', $defaults['delivery_date'])),
                  payment: @js($payment),
                  point: @js((string) old('collection_point_id', $points->first()->id ?? '')),
                  calc: @js($calc),
                  fits: @js($fits),
                  dayLists: @js($dayLists),
                  get dayList() {
                      return this.dayLists[this.method === 'pickup' ? 'pickup' : 'delivery|' + this.province] ?? [];
                  },
                  get summary() {
                      const key = this.method === 'pickup'
                          ? this.payment + '|pickup|' + this.day
                          : this.payment + '|' + this.province + '|' + this.day;
                      return this.calc[key] ?? null;
                  },
                  syncPayment() {
                      const allowed = this.fits[this.method] ?? [];
                      if (! allowed.includes(this.payment)) { this.payment = allowed[0] ?? ''; }
                  },
                  syncDay() {
                      const days = this.dayList;
                      if (! days.includes(this.day)) { this.day = days[0] ?? ''; }
                  },
              }"
              x-init="$watch('method', () => { syncPayment(); syncDay(); }); $watch('province', () => syncDay())"
              class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
            @csrf

            <div class="min-w-0 space-y-5">
                {{-- ۱. معلومات خریدار --}}
                <section class="card p-5 md:p-6">
                    <h2 class="font-bold text-gray-900">{{ __('checkout.purchaser.heading') }}</h2>
                    @guest('customer')
                        <p class="form-help mt-1">
                            {{ __('checkout.purchaser.guest_note') }}
                            <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('checkout.purchaser.login_link') }}</a>
                        </p>
                    @endguest

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-storefront.field name="purchaser_name" :label="__('checkout.purchaser.name')" :value="$defaults['purchaser_name']"
                                            required maxlength="100" autocomplete="name" :placeholder="__('checkout.purchaser.name_placeholder')" />
                        <x-storefront.field name="purchaser_phone" :label="__('checkout.purchaser.phone')" :value="$defaults['purchaser_phone']"
                                            required maxlength="20" dir="ltr" inputmode="tel" autocomplete="tel"
                                            :placeholder="__('checkout.purchaser.phone_placeholder')" :help="__('checkout.purchaser.phone_help')" />
                        <x-storefront.field name="purchaser_email" type="email" :label="__('checkout.purchaser.email')" :value="$defaults['purchaser_email']"
                                            maxlength="150" dir="ltr" autocomplete="email" :help="__('checkout.purchaser.email_help')" class="sm:col-span-2" />
                    </div>

                    {{-- گیرنده کس دیگری است؟ --}}
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <label class="flex items-start gap-2.5">
                            <input type="checkbox" name="is_gift" value="1" x-model="gift" class="mt-1 size-5 rounded border-gray-300 text-brand-600">
                            <span class="text-sm leading-6 text-gray-800">
                                {{ __('checkout.gift.label') }}
                                <span class="block text-xs text-gray-500">{{ __('checkout.gift.help') }}</span>
                            </span>
                        </label>

                        <div x-show="gift" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                            <x-storefront.field name="recipient_name" :label="__('checkout.gift.name')" required maxlength="100"
                                                :placeholder="__('checkout.gift.name_placeholder')" />
                            <x-storefront.field name="recipient_phone" :label="__('checkout.gift.phone')" required maxlength="20"
                                                dir="ltr" inputmode="tel" :placeholder="__('checkout.purchaser.phone_placeholder')"
                                                :help="__('checkout.gift.phone_help')" />
                        </div>
                    </div>
                </section>

                {{-- ۲. روش تحویل --}}
                <section class="card p-5 md:p-6">
                    <fieldset>
                        <legend class="font-bold text-gray-900">{{ __('checkout.delivery.heading') }}</legend>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-4"
                                   :class="method === 'delivery' ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                <input type="radio" name="delivery_method" value="delivery" x-model="method"
                                       class="mt-1 size-5 text-brand-600" @disabled(! $deliveryPossible)>
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('checkout.delivery.home') }}</span>
                                    <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('checkout.delivery.home_text') }}</span>
                                    @if (! $deliveryPossible)
                                        <span class="mt-1 block text-xs text-red-700">{{ __('checkout.errors.delivery_unavailable') }}</span>
                                    @endif
                                </span>
                            </label>

                            <label class="flex items-start gap-2.5 rounded-xl border p-4 {{ $pickupEnabled ? 'cursor-pointer' : 'opacity-60' }}"
                                   :class="method === 'pickup' ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                <input type="radio" name="delivery_method" value="pickup" x-model="method"
                                       class="mt-1 size-5 text-brand-600" @disabled(! $pickupEnabled)>
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('checkout.delivery.pickup') }}</span>
                                    <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('checkout.delivery.pickup_text') }}</span>
                                    @if (! $pickupEnabled)
                                        <span class="mt-1 block text-xs text-gray-500">{{ __('checkout.errors.pickup_unavailable') }}</span>
                                    @endif
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    {{-- آدرس و روز تحویل (درِ خانه) --}}
                    <div x-show="method === 'delivery'" x-cloak class="mt-5 border-t border-gray-100 pt-5">
                        <h3 class="text-sm font-bold text-gray-900">{{ __('checkout.delivery.address_heading') }}</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="f-province_id" class="form-label">
                                    {{ __('checkout.delivery.province') }} <span class="req" aria-hidden="true">*</span>
                                </label>
                                <select id="f-province_id" name="province_id" x-model="province" class="form-input">
                                    @foreach ($provinces as $province)
                                        <option value="{{ $province->id }}">{{ $province->tr('name') }}</option>
                                    @endforeach
                                </select>
                                <p class="form-help">{{ __('checkout.delivery.province_help', ['provinces' => $provinceNames]) }}</p>
                                @error('province_id')<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                            <x-storefront.field name="city" :label="__('checkout.delivery.city')" maxlength="100"
                                                autocomplete="address-level2" :placeholder="__('checkout.delivery.city_placeholder')" />
                            <x-storefront.field name="district" :label="__('checkout.delivery.district')" maxlength="100"
                                                :placeholder="__('checkout.delivery.district_placeholder')" />
                            <x-storefront.field name="address_line" type="textarea" rows="2" :label="__('checkout.delivery.address_line')"
                                                required maxlength="500" autocomplete="street-address"
                                                :placeholder="__('checkout.delivery.address_placeholder')" class="sm:col-span-2" />
                            <x-storefront.field name="delivery_instructions" type="textarea" rows="2" :label="__('checkout.delivery.instructions')"
                                                maxlength="500" :placeholder="__('checkout.delivery.instructions_placeholder')" class="sm:col-span-2" />
                        </div>

                        {{-- روز تحویل: برای هر ولایت جدا (بی بارگذاری دوباره‌ی صفحه) --}}
                        <fieldset class="mt-5">
                            <legend class="text-sm font-bold text-gray-900">
                                {{ __('checkout.delivery.day_heading') }} <span class="req" aria-hidden="true">*</span>
                            </legend>
                            <p class="form-help mt-1">{{ __('checkout.delivery.day_help') }} {{ __('checkout.delivery.weight', ['kg' => $kg]) }}</p>

                            @foreach ($provinces as $province)
                                <div x-show="province === @js((string) $province->id)" x-cloak class="mt-3 space-y-2">
                                    @forelse ($days[$province->id] ?? [] as $option)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3"
                                               :class="day === @js($option->dateString()) ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                            <input type="radio" name="delivery_date" value="{{ $option->dateString() }}" x-model="day"
                                                   class="size-5 text-brand-600" x-bind:disabled="method !== 'delivery' || province !== @js((string) $province->id)">
                                            <span class="flex-1 text-sm">
                                                <span class="font-medium text-gray-900">{{ $option->dayName() }}</span>
                                                <span class="text-gray-500"> — {{ $option->dateLong() }}</span>
                                            </span>
                                            <span class="text-sm font-bold text-gray-900">{{ Money::format($option->total) }}</span>
                                        </label>
                                    @empty
                                        <p class="alert alert-warning" role="alert">
                                            {{ ($tooHeavy[$province->id] ?? false) ? __('checkout.errors.delivery_too_heavy') : __('checkout.delivery.day_none') }}
                                        </p>
                                    @endforelse
                                </div>
                            @endforeach
                            @error('delivery_date')<p class="form-error">{{ $message }}</p>@enderror
                        </fieldset>
                    </div>

                    {{-- نمایندگی (تحویل حضوری) --}}
                    @if ($pickupEnabled)
                        <fieldset x-show="method === 'pickup'" x-cloak class="mt-5 border-t border-gray-100 pt-5">
                            <legend class="text-sm font-bold text-gray-900">{{ __('checkout.delivery.point') }}</legend>
                            <div class="mt-3 space-y-2">
                                @foreach ($points as $point)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                                           :class="point === @js((string) $point->id) ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                        <input type="radio" name="collection_point_id" value="{{ $point->id }}" x-model="point" class="mt-1 size-5 text-brand-600">
                                        <span class="text-sm">
                                            <span class="block font-medium text-gray-900">{{ $point->tr('name') }}</span>
                                            @if ($point->displayAddress())
                                                <span class="mt-0.5 block text-xs leading-6 text-gray-500">{{ $point->displayAddress() }}</span>
                                            @endif
                                            @if ($point->tr('hours'))
                                                <span class="block text-xs text-gray-500">{{ __('checkout.delivery.point_hours', ['hours' => $point->tr('hours')]) }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('collection_point_id')<p class="form-error">{{ $message }}</p>@enderror
                            @if ($store->pickupNote())
                                <p class="form-help mt-2">{{ $store->pickupNote() }}</p>
                            @endif

                            {{-- روز گرفتن جنس (بی کرایه): تا آن روز جنس در نمایندگی آماده می‌شود --}}
                            <div class="mt-5">
                                <p class="text-sm font-bold text-gray-900">
                                    {{ __('checkout.delivery.pickup_day_heading') }} <span class="req" aria-hidden="true">*</span>
                                </p>
                                <p class="form-help mt-1">{{ __('checkout.delivery.pickup_day_help') }}</p>
                                <div class="mt-3 space-y-2">
                                    @foreach ($pickupDays as $option)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3"
                                               :class="day === @js($option->dateString()) ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                            {{-- نام جدا از روز تحویل درِ خانه: اگر نام یکی باشد، مرورگر فقط آخرین
                                                 رادیوی هم‌نام را «انتخاب‌شده» نگه می‌دارد و روز درِ خانه از دست می‌رود. --}}
                                            <input type="radio" name="pickup_date" value="{{ $option->dateString() }}" x-model="day"
                                                   class="size-5 text-brand-600" x-bind:disabled="method !== 'pickup'">
                                            <span class="flex-1 text-sm">
                                                <span class="font-medium text-gray-900">{{ $option->dayName() }}</span>
                                                <span class="text-gray-500"> — {{ $option->dateLong() }}</span>
                                            </span>
                                            <span class="text-sm font-medium text-green-700">{{ __('checkout.totals.free') }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </fieldset>
                    @endif
                </section>

                {{-- ۳. روش پرداخت --}}
                <section class="card p-5 md:p-6">
                    <fieldset>
                        <legend class="font-bold text-gray-900">{{ __('checkout.payment.heading') }}</legend>
                        <div class="mt-4 space-y-2">
                            @foreach ($methods as $row)
                                @php $m = $row['method']; @endphp
                                <label x-show="(method === 'delivery' ? @js($row['delivery']) : @js($row['pickup']))"
                                       class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                                       :class="payment === @js($m->code) ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                                    <input type="radio" name="payment_method_code" value="{{ $m->code }}" x-model="payment" class="mt-1 size-5 text-brand-600">
                                    <span class="text-sm">
                                        <span class="block font-medium text-gray-900">{{ $m->tr('name') }}</span>
                                        @if ($m->tr('description'))
                                            <span class="mt-0.5 block text-xs leading-6 text-gray-500">{{ $m->tr('description') }}</span>
                                        @endif
                                        @if (Money::compare($m->fee, 0) > 0)
                                            <span class="mt-0.5 block text-xs text-gray-700">{{ __('checkout.totals.payment_fee') }}: {{ Money::format($m->fee) }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method_code')<p class="form-error">{{ $message }}</p>@enderror
                        @if ($methods->isEmpty())
                            <p class="alert alert-warning" role="alert">{{ __('checkout.payment.none') }}</p>
                        @endif
                        <p class="form-help mt-3">{{ __('checkout.payment.online_soon') }}</p>
                    </fieldset>

                    <div class="mt-5 border-t border-gray-100 pt-5">
                        <x-storefront.field name="customer_note" type="textarea" rows="2" :label="__('checkout.note.label')"
                                            maxlength="500" :placeholder="__('checkout.note.placeholder')" />
                    </div>
                </section>
            </div>

            {{-- خلاصه و دکمه‌ی ثبت --}}
            <aside class="card p-5 lg:sticky lg:top-28" aria-label="{{ __('checkout.totals.heading') }}">
                <h2 class="font-bold text-gray-900">{{ __('checkout.totals.heading') }}</h2>

                <ul class="mt-4 space-y-3 border-b border-gray-100 pb-4">
                    @foreach ($lines as $line)
                        <li class="flex items-start gap-3 text-sm">
                            <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-white">
                                @if ($line->image)
                                    <img src="{{ $line->image->url(300) }}" alt="{{ $line->image->altText($line->product->tr('name')) }}"
                                         width="48" height="48" loading="lazy" class="size-full object-contain">
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 text-gray-900">{{ $line->product->tr('name') }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500">
                                    {{ $line->variant->label ? $line->variant->label.' • ' : '' }}{{ __('checkout.order.qty', ['n' => Num::toFa((string) $line->qty)]) }}
                                </span>
                            </span>
                            <span class="font-medium whitespace-nowrap text-gray-900">{{ Money::format($line->lineTotal) }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- کد تخفیف: دکمه‌ها همین فورم را به آدرس دیگر می‌فرستند تا نوشته‌های مشتری بماند --}}
                <div class="mt-4 border-b border-gray-100 pb-4" id="discount-code">
                    @if ($discount)
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">
                            <span>{{ __('checkout.discount.applied_box', ['code' => $discount['code']]) }}</span>
                            <button type="submit" formaction="{{ route('checkout.discount.remove') }}" formnovalidate
                                    class="min-h-11 shrink-0 px-2 font-medium text-red-700 underline">{{ __('checkout.discount.remove') }}</button>
                        </div>
                    @else
                        <label for="discount_code" class="mb-1.5 block text-sm font-medium text-gray-800">{{ __('checkout.discount.label') }}</label>
                        <div class="flex gap-2">
                            <input type="text" id="discount_code" name="discount_code" maxlength="30" dir="ltr" autocomplete="off"
                                   value="{{ old('discount_code') }}" placeholder="{{ __('checkout.discount.placeholder') }}"
                                   aria-describedby="discount_code_help"
                                   class="form-input min-w-0 flex-1 uppercase @error('discount_code', 'discount') border-red-500 @enderror">
                            <button type="submit" formaction="{{ route('checkout.discount.apply') }}" formnovalidate
                                    class="btn btn-outline shrink-0">{{ __('checkout.discount.apply') }}</button>
                        </div>
                        @error('discount_code', 'discount')
                            <p class="mt-1.5 text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror
                        @if ($discountError)
                            <p class="mt-1.5 text-sm text-red-700" role="alert">{{ $discountError }}</p>
                        @endif
                        <p id="discount_code_help" class="mt-1.5 text-xs text-gray-500">{{ __('checkout.discount.phone_note') }}</p>
                    @endif
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-600">{{ __('checkout.totals.items', ['count' => Num::toFa((string) $totals['units'])]) }}</dt>
                        <dd class="font-medium text-gray-900">{{ Money::format($subtotal) }}</dd>
                    </div>
                    @if ($discount)
                        <div class="flex justify-between gap-3 text-green-700">
                            <dt>{{ __('checkout.discount.row_with_code', ['code' => $discount['code']]) }}</dt>
                            <dd class="font-medium">− {{ Money::format($discountAmount) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('checkout.discount.goods_after') }}</dt>
                            <dd class="font-medium text-gray-900">{{ Money::format($goods) }}</dd>
                        </div>
                    @endif
                    @if (Money::compare($totals['savings'], 0) > 0)
                        <div class="flex justify-between gap-3 text-red-700">
                            <dt>{{ __('checkout.totals.savings') }}</dt>
                            <dd class="font-medium">{{ Money::format($totals['savings']) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-600">{{ __('checkout.totals.delivery') }}</dt>
                        <dd class="font-medium text-gray-900">
                            <span x-text="summary ? summary.delivery : @js(__('checkout.totals.pick_day'))"></span>
                        </dd>
                    </div>
                    <template x-if="summary && summary.fee">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">{{ __('checkout.totals.payment_fee') }}</dt>
                            <dd class="font-medium text-gray-900" x-text="summary.fee"></dd>
                        </div>
                    </template>
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-3 text-base">
                        <dt class="font-bold text-gray-900">{{ __('checkout.totals.total') }}</dt>
                        <dd class="font-bold text-brand-800">
                            <span x-text="summary ? summary.total : @js(__('checkout.totals.pick_day'))"></span>
                        </dd>
                    </div>
                </dl>

                <button type="submit" class="btn btn-primary mt-5 w-full">{{ __('checkout.submit') }}</button>
                <p class="mt-2 text-xs leading-6 text-gray-500">{{ __('checkout.submit_help') }}</p>
                <a href="{{ route('cart.show') }}" class="btn btn-outline mt-3 w-full">{{ __('checkout.back_to_cart') }}</a>
            </aside>
        </form>
    </div>
@endsection
