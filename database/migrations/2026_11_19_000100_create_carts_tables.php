<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۴-۱: سبد خرید. سبد قیمت ذخیره نمی‌کند؛ هر بار نمایش، قیمت و موجودی زنده خوانده می‌شود.
 * مهمان: سبد با توکن در کوکی؛ عضو: سبد به مشتری وصل است و سبد مهمان بعد از ورود با آن ادغام می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->char('token', 36)->unique();                        // کوکی مهمان
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->cascadeOnDelete();
            $table->dateTime('expires_at');                              // پاک‌سازی سبدهای کهنه
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
