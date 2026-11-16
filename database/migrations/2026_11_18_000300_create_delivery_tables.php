<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کرایه‌ی تحویل (تصمیم صاحب پروژه، ۲۶ عقرب ۱۴۰۵) — مثل دیجی‌کالا:
 *
 *   کرایه = کرایه‌ی «روز تحویل» + اضافه‌ی «وزن بار»
 *
 * - روز تحویل: مشتری از چند روز پیش‌رو یکی را انتخاب می‌کند. زودترین روز گران‌تر (شروع از ۳۰۰ افغانی)
 *   و هر چه دیرتر، ارزان‌تر. هر ردیف `delivery_slots` = «n‌امین روز کاری پیش‌رو» (به ترتیب `day_rank`).
 * - وزن: پله‌های وزنی؛ هر چه سنگین‌تر، اضافه‌ی کرایه بیشتر.
 * همه‌ی عددها داده است و از پنل عوض می‌شود؛ هیچ عددی در کد نیست. هر منطقه (ولایت) قاعده‌ی خودش را دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            // خالی = هر ولایتی که منطقه‌ی خودش را ندارد (ولی «تحویل فعال» باشد)
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            // سفارش بعد از این ساعت (به وقت کابل) از فردا به حساب نمی‌آید، از پس‌فردا
            $table->unsignedTinyInteger('cutoff_hour')->default(16);
            // روزهای بی تحویل (روز هفته‌ی ISO: ۱ دوشنبه … ۵ جمعه … ۷ یکشنبه)
            $table->json('days_off')->nullable();
            // وزن جنسی که وزنش در پنل نوشته نشده (گرام)
            $table->unsignedInteger('default_item_weight_grams')->default(500);
            // سنگین‌تر از این را نمی‌رسانیم (گرام)؛ خالی = بی حد
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->timestamps();

            $table->unique('province_id');
        });

        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_rank');                 // ۱ = زودترین روز تحویل
            $table->decimal('fee', 12, 2);
            $table->boolean('is_active')->default(true);

            // بی unique: جابه‌جا کردن ترتیب در پنل ردیف به ردیف ذخیره می‌شود و unique وسط کار خطا می‌دهد
            $table->index(['zone_id', 'day_rank']);
        });

        Schema::create('delivery_weight_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->unsignedInteger('up_to_grams')->nullable();     // تا این وزن؛ خالی = «سنگین‌تر از همه‌ی پله‌ها»
            $table->decimal('extra_fee', 12, 2)->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->index(['zone_id', 'up_to_grams']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_weight_tiers');
        Schema::dropIfExists('delivery_slots');
        Schema::dropIfExists('delivery_zones');
    }
};
