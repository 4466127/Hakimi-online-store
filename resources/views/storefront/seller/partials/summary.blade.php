{{-- خلاصه‌ی درخواست فروشنده (قدم «ارسال» و صفحه‌ی وضعیت). $editable: لینک «ویرایش» هر بخش. --}}
@php
    $none = __('seller.onboarding.review.none');
    $sections = [
        'info' => [
            __('seller.fields.type') => $seller->type?->label(),
            __('seller.fields.full_name') => $seller->full_name,
            __('seller.fields.father_name') => $seller->father_name,
            __('seller.fields.tazkira_number') => $seller->tazkira_number,
            __('seller.fields.store_name') => $seller->store_name,
            __('seller.fields.business_name') => $seller->business_name,
            __('seller.fields.license_number') => $seller->license_number,
            __('seller.fields.category_ids') => $seller->categories()->map(fn ($c) => $c->tr('name'))->implode('، '),
            __('seller.fields.social_url') => $seller->social_url,
            __('seller.fields.brand_name') => $seller->brand_name,
            __('seller.fields.is_local_producer') => $seller->is_local_producer ? __('admin.yes') : null,
        ],
        'address' => [
            __('seller.fields.province_id') => $seller->province?->tr('name'),
            __('seller.fields.city') => $seller->city,
            __('seller.fields.address') => $seller->address,
            __('seller.fields.phone') => $seller->phone ? \App\Support\Phone::display($seller->phone) : null,
        ],
    ];
@endphp

<div class="mt-5 space-y-4">
    @foreach ($sections as $key => $rows)
        <section class="rounded-xl border border-gray-200">
            <header class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                <h3 class="text-sm font-bold text-gray-900">{{ __('seller.onboarding.steps.'.$key.'.title') }}</h3>
                @if ($editable ?? false)
                    <a href="{{ route('seller.step', ['step' => $key]) }}" class="inline-flex min-h-9 items-center gap-1 text-xs font-medium text-brand-700">
                        @svg('heroicon-o-pencil-square', 'size-4') {{ __('seller.onboarding.review.edit') }}
                    </a>
                @endif
            </header>
            <dl class="divide-y divide-gray-100 text-sm">
                @foreach ($rows as $label => $value)
                    @continue(($value === null || $value === '') && in_array($label, [__('seller.fields.business_name'), __('seller.fields.license_number'), __('seller.fields.social_url'), __('seller.fields.brand_name'), __('seller.fields.is_local_producer'), __('seller.fields.phone')], true))
                    <div class="grid grid-cols-[9rem_1fr] gap-2 px-4 py-2.5">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="break-words text-gray-900">{{ filled($value) ? $value : $none }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
