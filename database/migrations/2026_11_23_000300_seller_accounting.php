<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۴ برنامه‌ی کلان: قاعده‌های کمیسیون، دفتر فروشنده و تسویه.
 * شرح کامل: docs/COMMISSION_AND_SELLER_SETTLEMENT.md و DATABASE.md.
 *
 * فقط ستون و جدول تازه اضافه می‌شود؛ هیچ داده‌ای پاک نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱) نرخ ویژه‌ی فروشنده: درصدی یا مبلغ ثابت، روشن/خاموش، و «استثنا» که بر همه غلبه می‌کند
        Schema::table('seller_commission_rates', function (Blueprint $table) {
            $table->string('type', 10)->default('percent')->after('category_id');          // percent | fixed
            $table->decimal('fixed_amount', 12, 2)->nullable()->after('rate');             // برای هر عدد جنس
            $table->boolean('is_exception')->default(false)->after('fixed_amount');        // بر جنس و دسته غلبه می‌کند
            $table->boolean('is_active')->default(true)->after('is_exception');
        });
        // مزایای شروع (۰٪ اینستاگرامی، ۳٪ تولیدکننده‌ی محلی) همیشه «استثنا» با تاریخ پایان‌اند
        DB::table('seller_commission_rates')->where('reason', 'like', 'incentive.%')->update(['is_exception' => true]);

        // ۲) قاعده‌ی کمیسیون یک جنس مشخص
        Schema::create('product_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 10)->default('percent');                                  // percent | fixed
            $table->decimal('rate', 5, 2)->default(0);
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // ۳) کپی کامل قاعده در خط سفارش: مبلغ ثابت هر عدد (درصد از قبل بود)
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_fixed_amount', 12, 2)->nullable()->after('commission_rate');
        });

        // ۴) سند کمیسیون: پیوند با سفارش و نوع قاعده
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('source_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->after('order_id')->constrained('seller_orders')->restrictOnDelete();
            $table->string('commission_type', 10)->default('percent')->after('commission_rate');
            $table->char('currency_code', 3)->default('AFN')->after('seller_amount');
        });

        // ۵) دفتر فروشنده: شماره‌ی یکتا، پیوندها، و وضعیت تسویه‌ی هر ردیف
        Schema::table('seller_transactions', function (Blueprint $table) {
            $table->string('reference', 30)->nullable()->unique()->after('id');               // ST-100001
            $table->foreignId('order_id')->nullable()->after('seller_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->after('order_id')->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->after('seller_order_id')->constrained('payments')->restrictOnDelete();
            $table->char('currency_code', 3)->default('AFN')->after('balance_after');
            // چقدر از این ردیف هنوز تسویه نشده (علامت همان amount؛ ۰ = تمام)
            $table->decimal('open_amount', 12, 2)->default(0)->after('currency_code');
            // از این لحظه قابل تسویه است؛ خالی = هنوز نه (مثلاً پول هنوز دست پیک است)
            $table->dateTime('eligible_at')->nullable()->after('open_amount');

            $table->index(['seller_id', 'eligible_at']);
        });
        DB::table('seller_transactions')->update(['reference' => DB::raw("CONCAT('ST-', 100000 + id)")]);

        // ۶) کدام ردیف‌های دفتر با کدام تسویه (و چقدر) پرداخت شد — تسویه‌ی جزئی و جلوگیری از پرداخت دوباره
        Schema::create('seller_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('seller_settlements')->restrictOnDelete();
            $table->foreignId('seller_transaction_id')->constrained('seller_transactions')->restrictOnDelete();
            $table->decimal('amount', 12, 2);                 // علامت همان ردیف دفتر (برگشت‌ها منفی)
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['settlement_id', 'seller_transaction_id'], 'settlement_items_unique');
        });

        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->unique()->after('uuid');                // SP-100001
            $table->char('currency_code', 3)->default('AFN')->after('amount');
        });
        DB::table('seller_settlements')->update(['number' => DB::raw("CONCAT('SP-', 100000 + id)")]);
    }

    public function down(): void
    {
        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'currency_code']);
        });
        Schema::dropIfExists('seller_settlement_items');
        Schema::table('seller_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['seller_order_id']);
            $table->dropForeign(['payment_id']);
            $table->dropIndex(['seller_id', 'eligible_at']);
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'order_id', 'seller_order_id', 'payment_id', 'currency_code', 'open_amount', 'eligible_at']);
        });
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['seller_order_id']);
            $table->dropColumn(['order_id', 'seller_order_id', 'commission_type', 'currency_code']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('commission_fixed_amount');
        });
        Schema::dropIfExists('product_commission_rates');
        Schema::table('seller_commission_rates', function (Blueprint $table) {
            $table->dropColumn(['type', 'fixed_amount', 'is_exception', 'is_active']);
        });
    }
};
