<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۳-۲: اجناس، نوع‌ها (سایز × رنگ)، مشخصات، عکس‌ها، جنس مرتبط، ریدایرکت آدرس.
 *
 * products.seller_id خالی (NULL) = جنس خود بازارک (کمیسیون ندارد)؛ پر = جنس یک فروشنده‌ی تأییدشده.
 * پول DECIMAL(12,2) به افغانی. موجودی روی نوع (variant) است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('slug', 200)->unique();
            $table->json('name');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('status', 10)->default('draft');          // draft / active / inactive
            $table->boolean('is_featured')->default(false);
            $table->boolean('has_variants')->default(false);
            // cache از نوع‌های فعال (با ProductService::refresh ساخته می‌شود)
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->decimal('min_compare_price', 12, 2)->nullable(); // قیمت قبلی نوعِ ارزان‌ترین (برای نشان تخفیف روی کارت)
            $table->decimal('total_stock', 12, 3)->default(0);
            $table->text('search_text');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('is_featured');
            $table->index('seller_id');
            $table->fullText('search_text');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');                                 // بزرگ‌ترین نسخه
            $table->json('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->json('sizes');                                  // {"300": "…-300.webp", "600": "…", "1200": "…"}
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('barcode', 64)->nullable();
            $table->string('label', 100)->nullable();               // «۴۲ / سیاه» (خودکار از گزینه‌ها)
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();  // قیمت قبلی (برای تخفیف)
            $table->decimal('cost_price', 12, 2)->nullable();        // پنهان؛ برای گزارش سود
            $table->decimal('stock_qty', 12, 3)->default(0);
            $table->decimal('reserved_qty', 12, 3)->default(0);      // در سفارش‌های باز (مرحله‌ی ۴)
            $table->decimal('low_stock_threshold', 12, 3)->nullable();
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $table->timestamps();

            $table->index('product_id');
            $table->index('is_active');
            $table->index('barcode');
        });

        // سایز/رنگ هر نوع
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignId('attribute_option_id')->constrained('attribute_options')->cascadeOnDelete();

            $table->unique(['variant_id', 'attribute_id']);
            $table->index('attribute_option_id');
        });

        // مشخصات توصیفی جنس (پروسسور، ضمانت، جنس رویه، …)
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignId('attribute_option_id')->nullable()->constrained('attribute_options')->cascadeOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->boolean('value_bool')->nullable();

            // (جنس، مشخصه، گزینه): گزینه NULL برای text/number/boolean، پس یکتایی در سرویس هم نگه داشته می‌شود
            $table->unique(['product_id', 'attribute_id', 'attribute_option_id'], 'pav_unique');
            $table->index(['attribute_id', 'attribute_option_id']);
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('kind', 15)->default('related');          // related / accessory
            $table->unsignedInteger('position')->default(0);

            $table->unique(['product_id', 'related_product_id']);
        });

        // آدرس قدیمی که عوض شد ← به جدید ریدایرکت شود (لینک واتساپ و گوگل نمی‌شکند)
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 15);                              // product / category / page
            $table->string('old_slug', 200);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['type', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
