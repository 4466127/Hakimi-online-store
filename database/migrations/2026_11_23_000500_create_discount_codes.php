<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۹-۳: کد تخفیف (از جیب بازارک).
 *
 * - `discount_codes`: هر کد با نوع (درصدی/مبلغ ثابت)، حداقل خرید، سقف تخفیف، تاریخ، سقف استفاده‌ی کل و هر موبایل،
 *   «فقط خرید اول» و روشن/خاموش. `used_count` فقط زیر قفل ردیف کد عوض می‌شود.
 * - `discount_code_redemptions`: هر استفاده یک ردیف (هر سفارش حداکثر یک بار — UNIQUE). حذف نمی‌شود؛ اگر سفارش
 *   کامل لغو شد `status = released` می‌شود (تاریخچه می‌ماند). `returned_amount` = سهم تخفیفِ اجناس برگشتی.
 * - `orders.discount_code_id/discount_code`: کپی کد روی سفارش.
 * - `commission_entries.platform_discount`: سهم تخفیف بازارک هر خط در سند فروش (مثبت) و برگشت (منفی)؛
 *   «هزینه‌ی کدهای تخفیف بازارک» در گزارش مالی از جمع همین ستون است، جدا از کمیسیون و طلب فروشنده.
 *
 * فقط جدول و ستون تازه؛ هیچ داده‌ای پاک نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 10);                                   // percent | fixed
            $table->decimal('value', 12, 2);
            $table->decimal('min_order_total', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();          // فقط برای درصدی
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_phone')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('discount_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained('discount_codes')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('phone', 20);                                  // موبایل یکسان‌شده‌ی خریدار
            $table->decimal('amount', 12, 2);                            // تخفیف هنگام ثبت سفارش
            $table->decimal('returned_amount', 12, 2)->default(0);       // سهم اجناس برگشتی
            $table->string('status', 10)->default('active');              // active | released
            $table->dateTime('released_at')->nullable();
            $table->string('release_reason', 50)->nullable();
            $table->timestamps();
            $table->index(['discount_code_id', 'phone', 'status'], 'discount_redemptions_phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('discount_code_id')->nullable()->after('discount_total')->constrained('discount_codes')->nullOnDelete();
            $table->string('discount_code', 30)->nullable()->after('discount_code_id');
        });

        Schema::table('commission_entries', function (Blueprint $table) {
            $table->decimal('platform_discount', 12, 2)->default(0)->after('gross_amount');
        });
    }

    public function down(): void
    {
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropColumn('platform_discount');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn('discount_code');
        });
        Schema::dropIfExists('discount_code_redemptions');
        Schema::dropIfExists('discount_codes');
    }
};
