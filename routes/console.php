<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// فایل‌های موقت رهاشده‌ی بارگذاری عکس هر شب پاک می‌شود (روی سرور: cron هر دقیقه `php artisan schedule:run`)
Schedule::command('uploads:prune-temp')->dailyAt('03:30');
