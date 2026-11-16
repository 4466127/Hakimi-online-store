<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فروشندگان بازارک («در بازارک بفروشید»). هر فروشنده یک حساب مشتری است که فورم
 * ثبت‌نام فروشنده را پر کرده؛ مدیر درخواست را بررسی و تأیید یا رد می‌کند.
 * شرح ستون‌ها: DATABASE.md بخش ۳-د.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('status', 15)->default('draft');     // draft / pending / approved / rejected / suspended
            $table->unsignedTinyInteger('onboarding_step')->default(1);

            // قدم ۱: نوع و معلومات
            $table->string('type', 15)->nullable();              // individual / business
            $table->string('full_name', 100)->nullable();        // نام کامل مطابق تذکره
            $table->string('father_name', 100)->nullable();
            $table->string('tazkira_number', 30)->nullable()->unique();
            $table->string('store_name', 100)->nullable()->unique();
            $table->string('business_name', 150)->nullable();    // نام شرکت/دکان (حقوقی)
            $table->string('license_number', 50)->nullable();    // نمبر جواز تجارتی (حقوقی)
            $table->boolean('is_local_producer')->default(false);
            $table->boolean('is_social_seller')->default(false);
            $table->string('social_url')->nullable();
            $table->boolean('has_brand')->default(false);
            $table->string('brand_name', 100)->nullable();
            $table->json('category_ids')->nullable();            // دسته‌های اصلی که می‌خواهد بفروشد

            // قدم ۲: آدرس
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();              // تلفن دکان (اگر با حساب فرق دارد)

            // قدم ۳ و ۴: قوانین و ارسال
            $table->dateTime('terms_accepted_at')->nullable();
            $table->dateTime('submitted_at')->nullable();

            // بررسی مدیر
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();             // دلیل رد (به فروشنده نشان داده می‌شود)
            $table->text('admin_notes')->nullable();             // یادداشت داخلی مدیر

            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
