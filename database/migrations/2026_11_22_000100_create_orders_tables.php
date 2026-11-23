<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۲-۱ برنامه‌ی کلان (۱ قوس ۱۴۰۵): سفارش چندفروشنده.
 *
 * - `orders`: سفارش والد (یک تسویه، یک شماره). خریدار و گیرنده دو نفر جدا می‌توانند باشند.
 * - `seller_orders`: سفارش هر فروشنده با وضعیت و صورت مالی جدا (ستون‌های صورت مالی در
 *   `docs/COMMISSION_AND_SELLER_SETTLEMENT.md` بخش ۳؛ محاسبه‌ی کامل‌تر آنها مرحله‌ی ۴ است).
 * - `order_items`: خط‌ها با snapshot نام و قیمت و **کپی قاعده‌ی کمیسیون در لحظه‌ی ثبت سفارش**
 *   (تغییر بعدی نرخ روی گذشته اثر نمی‌گذارد).
 * - `order_status_history`: تاریخچه‌ی وضعیت سفارش، سفارش فروشنده، پرداخت و ... (فقط افزودن).
 * - `stock_movements`: دفتر حرکت موجودی آنلاین (فقط افزودن).
 *
 * هیچ داده‌ی موجودی را پاک نمی‌کند؛ فقط جدول تازه می‌سازد. پول DECIMAL(12,2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();                          // آدرس عمومی صفحه‌ی سفارش
            $table->string('number', 20)->nullable()->unique();          // HS-000123 (بلافاصله بعد از ساخته شدن پر می‌شود)
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // خریدار (کسی که پول می‌دهد)
            $table->string('purchaser_name', 100);
            $table->string('purchaser_phone', 20);
            $table->string('purchaser_email', 150)->nullable();
            $table->char('purchaser_country', 2)->default('AF');
            $table->boolean('is_gift')->default(false);                  // «برای کس دیگری می‌خرم»

            // گیرنده (کسی که جنس را می‌گیرد)
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->text('address_line')->nullable();
            $table->text('delivery_instructions')->nullable();

            $table->string('status', 15)->default('pending');           // pending|confirmed|processing|shipped|delivered|cancelled|returned
            $table->string('payment_status', 25)->default('unpaid');    // unpaid|awaiting_collection|pending|partially_paid|paid|failed|partially_refunded|refunded
            $table->string('payment_method_code', 30);

            $table->char('currency_code', 3)->default('AFN');
            $table->decimal('subtotal', 12, 2);                          // جمع خط‌ها
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('payment_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);                             // subtotal − discount_total + delivery_fee + payment_fee
            $table->decimal('paid_total', 12, 2)->default(0);            // فقط سرویس مالی عوض می‌کند
            $table->decimal('refunded_total', 12, 2)->default(0);

            $table->string('delivery_method', 10);                       // delivery (درِ خانه) | pickup (حضوری)
            $table->string('delivery_mode', 15)->default('consolidated'); // consolidated (شروع) | per_seller (بعداً)
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->date('delivery_date')->nullable();
            $table->unsignedInteger('delivery_weight_grams')->default(0);
            $table->unsignedBigInteger('collection_point_id')->nullable(); // تحویل حضوری: کدام نمایندگی (کلید خارجی بعد از ساخت جدول)

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('source', 10)->default('web');                // web | admin | whatsapp
            $table->boolean('needs_stock_check')->default(false);        // جنس «فروش بی موجودی»: پیش از تأیید با دکان چک شود
            $table->string('locale', 5)->default('fa');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->dateTime('placed_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index('customer_id');
            $table->index('purchaser_phone');
            $table->index('recipient_phone');
            $table->index('payment_status');
            $table->index('delivery_date');
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('number', 24)->unique();                      // HS-000123-1
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();   // خالی = جنس خود بازارک
            $table->string('status', 15)->default('pending');
            $table->string('payment_status', 25)->default('unpaid');

            // صورت مالی همین فروشنده (معادله‌ها: docs/COMMISSION_AND_SELLER_SETTLEMENT.md بخش ۳)
            $table->decimal('gross_subtotal', 12, 2);
            $table->decimal('seller_discount_total', 12, 2)->default(0);
            $table->decimal('platform_discount_total', 12, 2)->default(0);
            $table->decimal('customer_paid_products', 12, 2);
            $table->decimal('commissionable_amount', 12, 2);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);          // در حالت یکپارچه ۰ (کرایه روی سفارش والد)
            $table->string('delivery_fee_payee', 10)->default('platform'); // platform | seller
            $table->decimal('gateway_fee_allocated', 12, 2)->default(0);
            $table->decimal('gateway_fee_charged_to_seller', 12, 2)->default(0);
            $table->decimal('refund_adjustments', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('seller_payable', 12, 2)->default(0);

            $table->foreignId('assigned_courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handover_at')->nullable();                 // فروشنده جنس را به بازارک داد
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->text('seller_note')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index('order_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // snapshot: بعداً جنس عوض یا حذف شود، سفارش همان است که بود
            $table->string('product_name', 200);
            $table->string('variant_label', 100)->nullable();
            $table->string('sku', 60)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);       // سود نمایشی (قیمت قبلی − قیمت) برای کل خط
            $table->decimal('seller_discount', 12, 2)->default(0);
            $table->decimal('platform_discount', 12, 2)->default(0);
            $table->unsignedInteger('qty');
            $table->decimal('line_total', 12, 2);                        // unit_price × qty

            // کپی قاعده‌ی کمیسیون هنگام ثبت سفارش (ثابت؛ ثبت دفتر از همین‌ها می‌خواند)
            $table->unsignedBigInteger('commission_rule_id')->nullable();   // جدول قاعده‌ها مرحله‌ی ۴
            $table->string('commission_type', 10)->default('percent');       // percent | fixed
            $table->decimal('commission_rate', 7, 4)->default(0);
            $table->string('rate_source', 15)->default('platform');          // platform|seller|category|default|product|exception
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('commission_entry_id')->nullable();   // بعد از «تحویل + پول رسید» پر می‌شود

            $table->unsignedInteger('qty_cancelled')->default(0);
            $table->unsignedInteger('qty_returned')->default(0);
            $table->timestamps();

            $table->index('order_id');
            $table->index('seller_order_id');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);                          // order | seller_order | payment | refund | return
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // خالی = مشتری یا سیستم
            $table->text('note')->nullable();
            $table->dateTime('created_at');

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 15);                                  // adjust | reserve | release | sale | return
            $table->decimal('qty_delta', 12, 3);                         // تغییر «مقدار» همان ستونی که عوض شد
            $table->decimal('stock_after', 12, 3);
            $table->decimal('reserved_after', 12, 3);
            $table->string('reference_type', 30)->nullable();            // seller_order | order
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->dateTime('created_at');

            $table->index(['variant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('orders');
    }
};
