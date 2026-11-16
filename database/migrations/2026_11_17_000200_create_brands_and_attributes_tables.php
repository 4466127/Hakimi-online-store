<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرحله‌ی ۳-۱: برندها و مشخصه‌های کالا (سایز، رنگ، RAM، حافظه، …) و اینکه هر دسته کدام مشخصه‌ها را دارد.
 * مشخصه‌ها داده‌اند، نه کد: مدیر از پنل می‌سازد. شرح: DATABASE.md بخش ۳-پ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->string('name', 100);
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'position']);
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // size ، color ، ram …
            $table->json('name');
            $table->string('type', 15);                     // select / multiselect / text / number / boolean
            $table->string('unit', 20)->nullable();         // GB ، اینچ
            $table->boolean('is_variant')->default(false);  // نوع‌ساز (سایز، رنگ، حافظه)
            $table->boolean('is_filterable')->default(false);
            $table->boolean('show_in_specs')->default(true);
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('slug', 100);
            $table->json('value');
            $table->char('color_hex', 7)->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('category_attribute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->integer('position')->default(0);

            $table->unique(['category_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('brands');
    }
};
