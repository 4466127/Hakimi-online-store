<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * خواست صاحب پروژه (۲ قوس ۱۴۰۵، مثل دیجی‌کالا): **کد تحویل** برای هر سفارش.
 *
 * مشتری این پنج رقم را فقط هنگام گرفتن جنس به پیک (یا در نمایندگی) می‌گوید و بعد جنس تحویل
 * داده می‌شود. ثبت گرفتن پول و بررسی کد در مرحله‌ی ۳ (نقش پیک) به همین ستون وصل می‌شود.
 *
 * شماره‌ی سفارش از همین تاریخ **فقط عدد** است (`Order::numberFor`)؛ سفارش‌های قدیمیِ `HS-…`
 * دست نمی‌خورد و پیگیری هر دو شکل را قبول می‌کند.
 *
 * هیچ داده‌ای پاک نمی‌شود؛ فقط برای سفارش‌های موجود کد تحویل ساخته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_code', 10)->nullable()->after('number');
        });

        foreach (DB::table('orders')->whereNull('delivery_code')->pluck('id') as $id) {
            DB::table('orders')->where('id', $id)->update(['delivery_code' => (string) random_int(10000, 99999)]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_code');
        });
    }
};
