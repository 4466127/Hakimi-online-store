<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۹-۴: بنرهای صفحه‌ی اصلی (ساده: یک عکس، لینک، ترتیب، روشن/خاموش؛ حداکثر ۵ بنر روشن).
 * تاریخ نمایش، عکس جدای موبایل و شمارش کلیک فعلاً نیست (به خواست صاحب پروژه).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();              // متن جایگزین عکس (برای نابینایان و وقتی عکس نیامد)
            $table->string('image_path');
            $table->string('link_url', 255)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
