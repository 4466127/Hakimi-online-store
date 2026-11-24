<?php

use App\Services\Payments\OnlinePaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// فایل‌های موقت رهاشده‌ی بارگذاری عکس هر شب پاک می‌شود (روی سرور: cron هر دقیقه `php artisan schedule:run`)
Schedule::command('uploads:prune-temp')->dailyAt('03:30');

// سفارش آنلاین (حساب‌پی) که در وقتش پرداخت نشد لغو می‌شود تا موجودی آزاد شود (config store.payments.online_cancel_after_minutes)
Artisan::command('payments:cancel-unpaid-online', function (OnlinePaymentService $online) {
    $count = $online->cancelStale();
    $this->info("cancelled: {$count}");
})->purpose('Cancel online-payment orders that were never paid');
Schedule::command('payments:cancel-unpaid-online')->everyTenMinutes()->withoutOverlapping();
