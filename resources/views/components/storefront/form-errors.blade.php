{{-- لیست خطاهای فورم بالای فورم (کاربر ساده هم بفهمد چه چیز را درست کند). --}}
@if ($errors->any())
    <div class="alert alert-error mb-5" role="alert">
        <p class="font-bold">{{ __('store.form.errors_heading') }}</p>
        <ul class="mt-1 list-disc ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
