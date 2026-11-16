<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول‌های پایه‌ی مرحله‌ی ۲: تاریخچه‌ی فعالیت، تنظیمات، صفحه‌های ثابت، پول‌ها، ولایت‌ها.
 * شرح ستون‌ها: DATABASE.md بخش ۳.
 */
return new class extends Migration
{
    public function up(): void
    {
        // چه کسی چه کرد
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10)->default('system'); // user / customer / system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 50);                        // user.created، settings.updated، auth.login…
            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 150)->nullable();    // نام خوانا در همان لحظه (اگر بعداً پاک شد هم بماند)
            $table->json('changes')->nullable();                 // قبل/بعد
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index('created_at');
        });

        // تنظیمات فروشگاه (کلید/مقدار)
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->string('group', 30)->default('store');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('updated_at')->nullable();
        });

        // صفحه‌های ثابت: درباره‌ی ما، تماس، شرایط، …
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->json('title');
            $table->json('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->integer('position')->default(0);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        // پول‌ها. قیمت‌ها همیشه به افغانی ذخیره می‌شود؛ پول دیگر فقط برای نمایش.
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->json('name');
            $table->string('symbol', 10);
            $table->decimal('rate_to_afn', 18, 6)->default(1); // یک واحد = چند افغانی
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // ۳۴ ولایت افغانستان
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->json('name');
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            // آیا فعلاً به این ولایت جنس می‌رسانیم؟ (شروع: فقط کابل و غزنی؛ بقیه «زیر کار است»)
            $table->boolean('delivery_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_log');
    }
};
