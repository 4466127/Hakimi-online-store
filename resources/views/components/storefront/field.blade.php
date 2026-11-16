{{--
    یک خانه‌ی فورم: عنوان (با نشان لازم/اختیاری)، خانه، راهنما و خطا.
    مثال: <x-storefront.field name="phone" :label="__('...')" required inputmode="tel" dir="ltr" />
    برای textarea: type="textarea". رمز هیچ‌وقت دوباره پر نمی‌شود.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'help' => null,
    'value' => null,
    'placeholder' => null,
    'dir' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'rows' => 3,
])
@php
    $id = 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $hasError = $errors->has($name);
    $describedBy = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
    $current = $type === 'password' ? null : old($name, $value);
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($required)
            <span class="req" aria-hidden="true">*</span>
        @else
            <span class="text-xs font-normal text-gray-400">({{ __('store.form.optional') }})</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
                  @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                  @if ($maxlength) maxlength="{{ $maxlength }}" @endif
                  @if ($dir) dir="{{ $dir }}" @endif
                  @required($required)
                  @if ($hasError) aria-invalid="true" @endif
                  @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                  class="form-input">{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}"
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @if ($inputmode) inputmode="{{ $inputmode }}" @endif
               @if ($maxlength) maxlength="{{ $maxlength }}" @endif
               @if ($dir) dir="{{ $dir }}" @endif
               @required($required)
               @if ($hasError) aria-invalid="true" @endif
               @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
               class="form-input {{ $dir === 'ltr' ? 'text-start' : '' }}">
    @endif

    @if ($help)
        <p id="{{ $id }}-help" class="form-help">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="form-error">{{ $message }}</p>
    @enderror
</div>
