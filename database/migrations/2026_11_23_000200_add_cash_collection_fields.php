<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۳ برنامه‌ی کلان (۲ قوس ۱۴۰۵): گرفتن پول نقد با بررسی «کد تحویل».
 *
 * ستون‌های پول نقد (مبلغ گرفته‌شده، گیرنده‌ی پول، محل، پول دست پیک، شماره‌ی رسید) از همان
 * مرحله‌ی ۲-۱ در `payments` ساخته شده بود؛ اینجا فقط «کد تحویل بررسی شد؟» اضافه می‌شود
 * تا روی خود رسید سند بماند که جنس با کد درست تحویل داده شده.
 *
 * هیچ داده‌ای پاک نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // کد تحویل مشتری هنگام گرفتن پول بررسی شد (شاهد تحویل درست)
            $table->boolean('delivery_code_verified')->default(false)->after('payer_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('delivery_code_verified');
        });
    }
};
