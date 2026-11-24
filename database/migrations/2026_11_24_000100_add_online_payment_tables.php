<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پرداخت آنلاین با حساب‌پی (مرحله‌ی ۵، از روی مستندات رسمی docs.hesab.com — ۳ قوس ۱۴۰۵).
 *
 * - payments.gateway_session_id / gateway_checkout_url: نشست ۱۵ دقیقه‌ای صفحه‌ی پرداخت حساب‌پی
 *   (session_id برای تطبیق برگشت ناموفق؛ آدرس تا وقتی منقضی نشده دوباره استفاده می‌شود).
 *   شناسه‌ی تراکنش حساب‌پی بعد از پرداخت در `provider_reference` می‌نشیند (ستون موجود).
 * - payment_events: هر پیام سرور-به-سرور حساب‌پی یک بار (یکتایی provider + event_id)،
 *   با نتیجه‌ی پردازش و نشان «نیاز به بررسی» برای مدیر. امضا و کلید ذخیره نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_session_id', 64)->nullable()->after('provider_reference')->index();
            $table->string('gateway_checkout_url', 1000)->nullable()->after('gateway_session_id');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 120);
            $table->string('event_type', 30);                 // payment_success | payment_failure
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('reference', 64)->nullable();      // user_id ما (uuid پرداخت)
            $table->string('transaction_id', 64)->nullable(); // شناسه‌ی تراکنش حساب‌پی
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('outcome', 30);                    // paid | duplicate | amount_mismatch | …
            $table->boolean('needs_review')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('payload')->nullable();               // بی امضا
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'event_id']);
            $table->index(['needs_review', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_session_id']);
            $table->dropColumn(['gateway_session_id', 'gateway_checkout_url']);
        });
    }
};
