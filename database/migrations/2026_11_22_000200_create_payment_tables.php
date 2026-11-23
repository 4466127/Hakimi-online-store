<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۲-۱ (۱ قوس ۱۴۰۵): روش‌های پرداخت، نمایندگی‌ها و پرداخت‌ها.
 *
 * تصمیم صاحب پروژه: روش‌های MVP فقط `cod` (هنگام تحویل)، `cash_point` (نقد در نمایندگی) و `hesabpay`.
 * حواله با رسید و درگاه جدای «کارت بین‌المللی» ساخته نمی‌شود.
 * `payment_events` و `payment_allocations` در مرحله‌های ۴ و ۵ می‌آیند (وقتی کاری با آنها هست).
 * هیچ کلید و رمزی در این جدول‌ها نیست (فقط `.env`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                        // cod | cash_point | hesabpay
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('instructions')->nullable();                    // بعد از ثبت سفارش نشان داده می‌شود
            $table->string('type', 20);                                  // cod | cash_point | gateway
            $table->string('driver', 50)->nullable();                    // برای gateway
            $table->json('audience')->nullable();                        // ["domestic"] / ["international"] / هر دو؛ خالی = همه
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('min_total', 12, 2)->nullable();
            $table->decimal('max_total', 12, 2)->nullable();
            $table->json('config')->nullable();                          // غیرمحرمانه؛ کلید و رمز اینجا نه
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('collection_points', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('type', 10)->default('agent');                // office | agent
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();                         // خالی (دفتر مرکزی) = آدرس فروشگاه از تنظیمات
            $table->string('phone', 20)->nullable();
            $table->json('hours')->nullable();
            $table->json('instructions')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // مسئول
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('collection_point_id')->references('id')->on('collection_points')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();   // خالی = کل سفارش
            $table->string('method_code', 30);
            $table->string('provider', 30)->nullable();                  // hesabpay
            $table->decimal('amount', 12, 2);                            // مبلغ مورد انتظار
            $table->decimal('fee_amount', 12, 2)->default(0);            // کارمزد درگاه (از جواب درگاه)
            $table->char('currency_code', 3)->default('AFN');
            $table->string('status', 25);                                // awaiting_collection|pending|paid|failed|refunded|partially_refunded
            $table->string('failed_reason', 30)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            // پول نقد
            $table->decimal('amount_received', 12, 2)->nullable();       // پول واقعی گرفته‌شده
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('collected_at_type', 10)->nullable();         // door (پیک، دمِ در) | point (نمایندگی)
            $table->foreignId('collection_point_id')->nullable()->constrained('collection_points')->nullOnDelete();
            $table->dateTime('collected_at')->nullable();
            $table->string('courier_cash_status', 15)->nullable();       // held_by_courier | handed_over
            $table->foreignId('handed_over_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handed_over_at')->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();  // RC-…

            $table->string('payer_name', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['collection_point_id']);
        });
        Schema::dropIfExists('collection_points');
        Schema::dropIfExists('payment_methods');
    }
};
