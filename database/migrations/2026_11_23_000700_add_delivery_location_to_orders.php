<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موقعیت تحویل روی نقشه (اختیاری): عرض و طول جغرافیایی کنار آدرس نوشتاری، روی خود سفارش (کپی همان لحظه).
 * `delivery_location_source`: map (نشانه را روی نقشه گذاشت) | gps (موقعیت فعلی دستگاه).
 * فقط ستون تازه؛ هیچ داده‌ای پاک نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('address_line');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            $table->string('delivery_location_source', 10)->nullable()->after('delivery_lng');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng', 'delivery_location_source']);
        });
    }
};
