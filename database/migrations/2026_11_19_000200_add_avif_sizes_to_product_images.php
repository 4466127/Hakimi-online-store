<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسخه‌ی AVIF عکس جنس (حدود ۲۰٪ کوچک‌تر از WebP). اگر سرور AVIF نداشته باشد خالی می‌ماند و فقط WebP نشان داده می‌شود.
 * فرمت: {"300": "…-300.avif", "600": "…", "1200": "…"}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->json('avif_sizes')->nullable()->after('sizes');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('avif_sizes');
        });
    }
};
