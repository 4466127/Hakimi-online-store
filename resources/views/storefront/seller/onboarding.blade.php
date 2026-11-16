{{--
    فورم چهارقدمی ثبت‌نام فروشنده (الگو: ثبت‌نام فروشنده‌ی دیجی‌کالا).
    کنار صفحه: قدم‌ها (تمام‌شده ✓، فعلی، قفل). وسط: فورم همان قدم.
    به‌جای «کد ملی» نمبر تذکره‌ی افغانستان گرفته می‌شود.
--}}
@extends('storefront.layouts.app')

@section('title', __('seller.onboarding.title'))
@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
    @php
        $deliveryText = $store->deliveryProvincesText();
        $steps = \App\Models\Seller::STEPS;
        $prevKey = $steps[$step - 1] ?? null;
        $selectedCategories = array_map('intval', old('category_ids', $seller->category_ids ?? []));
    @endphp

    <div class="container-page py-6 md:py-10">
        <div class="grid gap-6 md:grid-cols-[15rem_1fr]">
            {{-- قدم‌ها --}}
            <aside aria-label="{{ __('seller.onboarding.title') }}">
                <ol class="card flex gap-1 overflow-x-auto p-3 md:flex-col md:gap-0 md:overflow-visible md:p-4">
                    @foreach ($steps as $number => $key)
                        @php
                            $isCurrent = $number === $step;
                            $isDone = $number < $seller->onboarding_step && ! $isCurrent;
                            $isOpen = $seller->canOpenStep($number);
                        @endphp
                        <li class="relative shrink-0 md:pb-5 md:last:pb-0">
                            @if (! $loop->last)
                                <span class="absolute top-10 bottom-0 hidden w-0.5 md:block {{ $number < $seller->onboarding_step ? 'bg-brand-600' : 'bg-gray-200' }}" style="inset-inline-start: 1.1875rem" aria-hidden="true"></span>
                            @endif
                            @if ($isOpen && ! $isCurrent)
                                <a href="{{ route('seller.step', ['step' => $key]) }}" class="relative flex items-center gap-3 rounded-xl px-1 py-1 hover:bg-gray-50">
                            @else
                                <div class="relative flex items-center gap-3 px-1 py-1" @if ($isCurrent) aria-current="step" @endif>
                            @endif
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full
                                    {{ $isCurrent ? 'bg-brand-700 text-white ring-4 ring-brand-100' : ($isDone ? 'bg-brand-600 text-white' : ($isOpen ? 'bg-brand-50 text-brand-700' : 'bg-gray-200 text-gray-500')) }}">
                                    @if ($isDone)
                                        @svg('heroicon-m-check', 'size-5')
                                    @elseif (! $isOpen)
                                        @svg('heroicon-m-lock-closed', 'size-4')
                                    @else
                                        <span class="text-sm font-bold">{{ \App\Support\Num::toFa((string) $number) }}</span>
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold {{ $isOpen ? 'text-gray-900' : 'text-gray-400' }}">{{ __('seller.onboarding.steps.'.$key.'.title') }}</span>
                                    <span class="hidden text-xs text-gray-500 md:block">{{ implode('، ', __('seller.onboarding.steps.'.$key.'.items')) }}</span>
                                </span>
                            @if ($isOpen && ! $isCurrent)
                                </a>
                            @else
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </aside>

            {{-- فورم قدم فعلی --}}
            <div class="min-w-0">
                <div class="mb-4 flex items-center gap-2">
                    @if ($prevKey)
                        <a href="{{ route('seller.step', ['step' => $prevKey]) }}" class="btn btn-outline size-11 px-0" aria-label="{{ __('seller.onboarding.back') }}">
                            @svg('heroicon-o-arrow-right', 'size-5')
                        </a>
                    @endif
                    <h1 class="text-lg font-bold text-gray-900 md:text-xl">{{ __('seller.onboarding.'.$stepKey.'.heading') }}</h1>
                </div>

                @if ($seller->status->value === 'rejected' && $seller->review_note)
                    <div class="alert alert-warning mb-4">{{ __('seller.onboarding.rejected_banner', ['reason' => $seller->review_note]) }}</div>
                @endif

                <div class="card p-5 md:p-7">
                    <x-storefront.form-errors />

                    {{-- ───── قدم ۱: معلومات ───── --}}
                    @if ($stepKey === 'info')
                        <form method="post" action="{{ route('seller.info') }}" novalidate
                              x-data="{ type: @js(old('type', $seller->type?->value ?? 'individual')), social: @js((bool) old('is_social_seller', $seller->is_social_seller)), brand: @js((bool) old('has_brand', $seller->has_brand)) }">
                            @csrf
                            <p class="mb-5 text-xs text-gray-500">{{ __('seller.onboarding.required_note') }}</p>

                            <fieldset>
                                <legend class="form-label">{{ __('seller.onboarding.info.type_heading') }} <span class="req">*</span></legend>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach (['individual' => 'heroicon-o-user', 'business' => 'heroicon-o-building-storefront'] as $type => $icon)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                                               :class="type === '{{ $type }}' ? 'border-brand-600 bg-brand-50' : 'border-gray-200'">
                                            <input type="radio" name="type" value="{{ $type }}" x-model="type" class="form-check mt-0.5" @checked(old('type', $seller->type?->value ?? 'individual') === $type)>
                                            <span>
                                                <span class="flex items-center gap-1.5 text-sm font-bold text-gray-900">@svg($icon, 'size-5 text-brand-700') {{ __('seller.types.'.$type.'.title') }}</span>
                                                <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('seller.types.'.$type.'.text') }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <h2 class="mt-8 mb-4 border-t border-gray-100 pt-6 font-bold text-gray-900">{{ __('seller.onboarding.info.details_heading') }}</h2>

                            <div class="alert mb-5 flex items-center gap-2 border-gray-300 bg-gray-100 text-gray-700">
                                @svg('heroicon-o-information-circle', 'size-5 shrink-0')
                                {{ __('seller.onboarding.info.tazkira_notice') }}
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-storefront.field name="full_name" :label="__('seller.fields.full_name')" :value="$seller->full_name" required maxlength="100" :placeholder="__('seller.placeholders.full_name')" />
                                <x-storefront.field name="father_name" :label="__('seller.fields.father_name')" :value="$seller->father_name" required maxlength="100" :placeholder="__('seller.placeholders.father_name')" />
                                <x-storefront.field name="tazkira_number" :label="__('seller.fields.tazkira_number')" :value="$seller->tazkira_number" required maxlength="30" dir="ltr" inputmode="numeric"
                                                    :placeholder="__('seller.placeholders.tazkira_number')" :help="__('seller.help.tazkira_number')" />
                                <x-storefront.field name="store_name" :label="__('seller.fields.store_name')" :value="$seller->store_name" required maxlength="100"
                                                    :placeholder="__('seller.placeholders.store_name')" :help="__('seller.help.store_name')" />
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="type === 'business'" x-cloak>
                                <x-storefront.field name="business_name" :label="__('seller.fields.business_name')" :value="$seller->business_name" required maxlength="150" :placeholder="__('seller.placeholders.business_name')" />
                                <x-storefront.field name="license_number" :label="__('seller.fields.license_number')" :value="$seller->license_number" required maxlength="50" dir="ltr" :placeholder="__('seller.placeholders.license_number')" />
                            </div>

                            {{-- دسته‌ها --}}
                            <fieldset class="mt-6">
                                <legend class="form-label">{{ __('seller.fields.category_ids') }} <span class="req">*</span></legend>
                                <p class="form-help -mt-1 mb-2">{{ __('seller.onboarding.info.categories_help') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($rootCategories as $category)
                                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-gray-200 px-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="form-check" @checked(in_array($category->id, $selectedCategories, true))>
                                            {{ $category->tr('name') }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('category_ids')<p class="form-error">{{ $message }}</p>@enderror
                            </fieldset>

                            {{-- نشان‌ها (اختیاری) --}}
                            <p class="mt-8 border-t border-gray-100 pt-6 text-sm leading-7 text-gray-700">{{ __('seller.onboarding.info.flags_intro') }}</p>
                            <div class="mt-3 divide-y divide-gray-100">
                                <label class="flex cursor-pointer items-start gap-3 py-4">
                                    <input type="checkbox" name="is_local_producer" value="1" class="form-check mt-0.5" @checked(old('is_local_producer', $seller->is_local_producer))>
                                    <span>
                                        <span class="block text-sm font-bold text-gray-900">{{ __('seller.fields.is_local_producer') }}</span>
                                        <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('seller.help.is_local_producer') }}</span>
                                    </span>
                                </label>
                                <div class="py-4">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="checkbox" name="is_social_seller" value="1" x-model="social" class="form-check mt-0.5" @checked(old('is_social_seller', $seller->is_social_seller))>
                                        <span>
                                            <span class="block text-sm font-bold text-gray-900">{{ __('seller.fields.is_social_seller') }}</span>
                                            <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('seller.help.is_social_seller') }}</span>
                                        </span>
                                    </label>
                                    <div class="mt-3 ps-8" x-show="social" x-cloak>
                                        <x-storefront.field name="social_url" type="url" :label="__('seller.fields.social_url')" :value="$seller->social_url" required dir="ltr" maxlength="255" :placeholder="__('seller.placeholders.social_url')" />
                                    </div>
                                </div>
                                <div class="py-4">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input type="checkbox" name="has_brand" value="1" x-model="brand" class="form-check mt-0.5" @checked(old('has_brand', $seller->has_brand))>
                                        <span>
                                            <span class="block text-sm font-bold text-gray-900">{{ __('seller.fields.has_brand') }}</span>
                                            <span class="mt-1 block text-xs leading-6 text-gray-500">{{ __('seller.help.has_brand') }}</span>
                                        </span>
                                    </label>
                                    <div class="mt-3 ps-8" x-show="brand" x-cloak>
                                        <x-storefront.field name="brand_name" :label="__('seller.fields.brand_name')" :value="$seller->brand_name" required maxlength="100" :placeholder="__('seller.placeholders.brand_name')" />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end border-t border-gray-100 pt-5">
                                <button type="submit" class="btn btn-primary">{{ __('seller.onboarding.next') }} @svg('heroicon-m-chevron-left', 'size-4')</button>
                            </div>
                        </form>

                    {{-- ───── قدم ۲: آدرس ───── --}}
                    @elseif ($stepKey === 'address')
                        <form method="post" action="{{ route('seller.address') }}" novalidate>
                            @csrf
                            <p class="mb-5 text-sm leading-7 text-gray-600">{{ __('seller.onboarding.address.intro') }}</p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="f-province_id" class="form-label">{{ __('seller.fields.province_id') }} <span class="req">*</span></label>
                                    <select id="f-province_id" name="province_id" required class="form-input" @error('province_id') aria-invalid="true" @enderror>
                                        <option value="">—</option>
                                        @foreach ($provinces as $province)
                                            <option value="{{ $province->id }}" @selected((int) old('province_id', $seller->province_id) === $province->id)>
                                                {{ $province->tr('name') }}{{ $province->delivery_enabled ? '' : ' — '.__('store.home.delivery_soon') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('province_id')<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <x-storefront.field name="city" :label="__('seller.fields.city')" :value="$seller->city" required maxlength="100" :placeholder="__('seller.placeholders.city')" />
                                <x-storefront.field name="address" type="textarea" :label="__('seller.fields.address')" :value="$seller->address" required maxlength="500" :placeholder="__('seller.placeholders.address')" class="sm:col-span-2" />
                                <x-storefront.field name="phone" type="tel" :label="__('seller.fields.phone')" :value="$seller->phone" dir="ltr" inputmode="tel" maxlength="20" :placeholder="__('seller.placeholders.phone')" />
                            </div>

                            <p class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                                @svg('heroicon-o-map-pin', 'size-4') {{ __('seller.onboarding.address.map_note') }}
                            </p>

                            <div class="mt-6 flex justify-end border-t border-gray-100 pt-5">
                                <button type="submit" class="btn btn-primary">{{ __('seller.onboarding.next') }} @svg('heroicon-m-chevron-left', 'size-4')</button>
                            </div>
                        </form>

                    {{-- ───── قدم ۳: قوانین ───── --}}
                    @elseif ($stepKey === 'rules')
                        <form method="post" action="{{ route('seller.rules') }}" novalidate>
                            @csrf
                            <p class="text-sm leading-7 text-gray-600">{{ __('seller.onboarding.rules.intro') }}</p>
                            <ol class="mt-4 space-y-3">
                                @foreach (__('seller.onboarding.rules.items') as $rule)
                                    <li class="flex items-start gap-3 text-sm leading-7 text-gray-700">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ \App\Support\Num::toFa((string) $loop->iteration) }}</span>
                                        <span>{{ str_replace(':provinces', $deliveryText, $rule) }}</span>
                                    </li>
                                @endforeach
                            </ol>

                            <label class="mt-6 flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="checkbox" name="terms" value="1" class="form-check mt-0.5" required @checked(old('terms', $seller->terms_accepted_at !== null))>
                                <span class="text-sm font-bold text-gray-900">{{ __('seller.onboarding.rules.accept') }}</span>
                            </label>

                            <div class="mt-6 flex justify-end border-t border-gray-100 pt-5">
                                <button type="submit" class="btn btn-primary">{{ __('seller.onboarding.next') }} @svg('heroicon-m-chevron-left', 'size-4')</button>
                            </div>
                        </form>

                    {{-- ───── قدم ۴: بررسی و ارسال ───── --}}
                    @else
                        <p class="text-sm leading-7 text-gray-600">{{ __('seller.onboarding.review.intro') }}</p>

                        @include('storefront.seller.partials.summary', ['seller' => $seller, 'editable' => true])

                        @unless ($isComplete)
                            <div class="alert alert-warning mt-5">{{ __('seller.onboarding.review.incomplete') }}</div>
                        @endunless

                        <form method="post" action="{{ route('seller.submit') }}" class="mt-6 flex justify-end border-t border-gray-100 pt-5">
                            @csrf
                            <button type="submit" class="btn btn-accent px-6" @disabled(! $isComplete)>
                                @svg('heroicon-o-paper-airplane', 'size-5') {{ __('seller.onboarding.review.submit') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
