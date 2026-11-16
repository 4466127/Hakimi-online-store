{{-- پیام بعد از یک کار: موفقیت (session «status»)، هشدار («warning») و خطا («error»). --}}
@if (session('status'))
    <div class="alert alert-success mb-5 flex items-start gap-2" role="status">
        @svg('heroicon-o-check-circle', 'mt-1 size-5 shrink-0')
        <span>{{ session('status') }}</span>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning mb-5 flex items-start gap-2" role="status">
        @svg('heroicon-o-exclamation-triangle', 'mt-1 size-5 shrink-0')
        <span>{{ session('warning') }}</span>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-error mb-5 flex items-start gap-2" role="alert">
        @svg('heroicon-o-x-circle', 'mt-1 size-5 shrink-0')
        <span>{{ session('error') }}</span>
    </div>
@endif
