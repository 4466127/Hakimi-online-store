<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندی کالاها (درختی، چندسطحی). منوی «دسته‌بندی کالاها» از همین جدول ساخته می‌شود.
 * شرح ستون‌ها: DATABASE.md بخش ۳-پ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug', 150)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon', 50)->nullable();
            $table->integer('position')->default(0);
            $table->unsignedTinyInteger('depth')->default(0);   // ۰ = دسته‌ی اصلی
            $table->string('path')->default('');                // شناسه‌های بالادست با «/»: «1/5/12»
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->unsignedInteger('products_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'position']);
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
