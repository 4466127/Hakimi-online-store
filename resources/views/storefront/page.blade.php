{{-- صفحه‌ی ثابت (درباره‌ی ما، تماس، شرایط…). متن را مدیر در پنل می‌نویسد. --}}
@extends('storefront.layouts.app')

@section('title', $page->tr('meta_title') ?: $page->tr('title'))
@section('description', $description)

@section('content')
    <article class="container-page mt-6 md:mt-10">
        <nav class="mb-4 text-sm text-gray-500" aria-label="breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('store.nav.home') }}</a>
            <span class="mx-1" aria-hidden="true">/</span>
            <span class="text-gray-700">{{ $page->tr('title') }}</span>
        </nav>

        <div class="card p-5 md:p-8">
            <h1 class="mb-6 text-2xl font-bold text-gray-900">{{ $page->tr('title') }}</h1>

            {{-- متن ویرایشگر پیش از نمایش پاک‌سازی می‌شود (فقط تگ‌های بی‌خطر می‌ماند) --}}
            <div class="prose-page">
                {!! $body !!}
            </div>
        </div>
    </article>
@endsection
