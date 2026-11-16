<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * کمیسیون بازارک و حساب فروشندگان. شرح: DATABASE.md بخش ۳-ر، ARCHITECTURE.md بخش ۷-۱۴.
 *
 * - نرخ کمیسیون هر دسته (categories.commission_rate؛ خالی = از دسته‌ی بالادست، بعد پیش‌فرض تنظیمات)
 * - نرخ ویژه‌ی هر فروشنده (seller_commission_rates)
 * - ثبت کمیسیون هر فروش تحویل‌شده با نرخ همان لحظه (commission_entries؛ یکتا، بی تکرار)
 * - حساب فروشنده: طلب، برگشت، تسویه (seller_transactions، seller_settlements)
 */
return new class extends Migration
{
    /** نرخ پیش‌فرض صاحب پروژه (۲۶ عقرب ۱۴۰۵) برای دسته‌هایی که از قبل ساخته شده‌اند. */
    private const DEFAULT_RATES = [
        'mobile-phones' => 3,
        'computers' => 4,
        'mobile-accessories' => 6,
        'computer-accessories' => 6,
        'shoes' => 7,
        'clothing' => 7,
        'bags-accessories' => 7,
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('icon');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable()->after('reviewed_at');          // اولین تأیید (برای مزایا)
            $table->string('settlement_cycle', 10)->default('monthly')->after('approved_at'); // monthly / biweekly / weekly
            $table->date('free_pickup_until')->nullable()->after('settlement_cycle');   // گرفتن رایگان جنس از دکان تا این روز
            $table->dateTime('incentives_applied_at')->nullable()->after('free_pickup_until');
        });

        // نرخ ویژه‌ی یک فروشنده (همه‌ی دسته‌ها یا یک دسته)، با تاریخ شروع و ختم اختیاری
        Schema::create('seller_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete(); // خالی = همه
            $table->decimal('rate', 5, 2);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('reason', 50)->nullable();     // incentive.social ، manual ، …
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['seller_id', 'starts_at', 'ends_at']);
        });

        // تسویه‌ها: پولی که به فروشنده داده شد
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);                  // cash / hesabpay / bank_transfer
            $table->string('reference', 100)->nullable();  // شماره‌ی رسید یا تراکنش
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['seller_id', 'paid_at']);
        });

        // سند مالی هر فروش (یا برگشت). هیچ‌وقت ویرایش یا حذف نمی‌شود.
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key', 120)->unique(); // order_item:15:sale — جلوی ثبت دوباره را می‌گیرد
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->restrictOnDelete(); // خالی = فروش خود بازارک
            $table->string('kind', 10);                    // sale / reversal
            $table->string('source_type', 30);             // order_item ، order_return ، manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('gross_amount', 12, 2);        // مبلغ جنس بی کرایه (برگشت: منفی)
            $table->decimal('commission_rate', 5, 2);      // نرخ همان لحظه
            $table->string('rate_source', 15);             // seller / category / default / platform
            $table->decimal('commission_amount', 12, 2);   // سهم بازارک
            $table->decimal('seller_amount', 12, 2);       // سهم فروشنده
            $table->foreignId('reverses_id')->nullable()->constrained('commission_entries')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['seller_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('occurred_at');
        });

        // دفتر حساب فروشنده: هر ردیف طلب (+) یا برداشت (−) با مانده‌ی بعد از آن
        Schema::create('seller_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('type', 15);                    // earning / reversal / payout / adjustment
            $table->decimal('amount', 12, 2);              // مثبت = طلب فروشنده زیاد شد
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('commission_entry_id')->nullable()->unique()->constrained('commission_entries')->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->unique()->constrained('seller_settlements')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['seller_id', 'id']);
        });

        // نرخ پیش‌فرض صاحب پروژه روی دسته‌های موجود (فقط اگر نرخی نگذاشته باشد)
        foreach (self::DEFAULT_RATES as $slug => $rate) {
            DB::table('categories')->where('slug', $slug)->whereNull('commission_rate')->update(['commission_rate' => $rate]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_transactions');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('seller_settlements');
        Schema::dropIfExists('seller_commission_rates');

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'settlement_cycle', 'free_pickup_until', 'incentives_applied_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
