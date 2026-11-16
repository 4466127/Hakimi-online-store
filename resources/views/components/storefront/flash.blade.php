{{-- پیام موفقیت بعد از ذخیره (session «status»). --}}
@if (session('status'))
    <div class="alert alert-success mb-5 flex items-start gap-2" role="status">
        @svg('heroicon-o-check-circle', 'mt-1 size-5 shrink-0')
        <span>{{ session('status') }}</span>
    </div>
@endif
