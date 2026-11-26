<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «کد تخفیف در سایت» (خواست صاحب پروژه، ۵ قوس ۱۴۰۵؛ الگو: جاکدی دیجی‌کالا، بی بازی و قرعه):
 * مدیر یک کد را «نمایش در سایت» می‌زند؛ صفحه‌ی اصلی کارت کد را با دکمه‌ی کاپی و شمارش معکوس تا
 * پایان کد نشان می‌دهد و صفحه‌ی تسویه آن را پیشنهاد می‌کند. فقط یک کد در یک وقت نشان داده می‌شود.
 * هیچ داده‌ای عوض نمی‌شود؛ پیش‌فرض «نمایش نده».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table): void {
            $table->boolean('show_on_site')->default(false)->after('is_active');
            $table->json('promo_title')->nullable()->after('show_on_site');   // متن کوتاه کارت، چندزبانه
            $table->index(['show_on_site', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table): void {
            $table->dropIndex(['show_on_site', 'is_active']);
            $table->dropColumn(['show_on_site', 'promo_title']);
        });
    }
};
