<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۹-۲: «از کدام تبلیغ آمد؟» — برچسب‌های utm روی سفارش. فقط ستون تازه؛ هیچ داده‌ای پاک نمی‌شود.
 * (ستون `source` = web/admin/whatsapp از قبل هست و همان می‌ماند.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('utm_source', 50)->nullable()->after('source');
            $table->string('utm_medium', 50)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->index(['utm_source', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['utm_source', 'placed_at']);
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
