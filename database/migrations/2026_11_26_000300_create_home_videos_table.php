<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ویدیوهای صفحه‌ی اصلی (خواست صاحب پروژه، ۵ قوس ۱۴۰۵؛ الگو: ویدیوی جنس در صفحه‌ی اصلی دیجی‌کالا):
 * یک ویدیوی کوتاه (mp4/webm، از خود سرور، بی CDN)، عکس پیش‌نمایش (WebP)، عنوان، لینک و چند جنس زیر ویدیو.
 * ویدیو بی صدا و فقط وقتی دیده می‌شود پخش می‌شود؛ با «صرفه‌جویی داده» یا «حرکت کمتر» خودکار پخش نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_videos', function (Blueprint $table): void {
            $table->id();
            $table->json('title');                                  // چندزبانه، مثلاً {"fa": "بوت‌های زمستانی"}
            $table->string('video_path');                           // نام تصادفی در دیسک public/videos
            $table->string('poster_path');                          // WebP ساخته‌شده با ImageStore
            $table->string('link_url', 255)->nullable();
            $table->json('product_ids')->nullable();                // حداکثر ۸ جنس زیر ویدیو
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_videos');
    }
};
