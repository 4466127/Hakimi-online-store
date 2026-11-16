<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مشتریان فروشگاه (جدا از کاربران پنل). با شماره‌ی تلفن و رمز وارد می‌شوند.
 * شرح ستون‌ها: DATABASE.md بخش ۳-ب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('phone', 20)->unique();             // 07XXXXXXXX
            $table->dateTime('phone_verified_at')->nullable();  // بعد از پیامک واقعی (مرحله‌ی ۵)
            $table->string('email', 150)->nullable()->unique();
            $table->string('password')->nullable();             // مهمان رمز ندارد
            $table->boolean('is_guest')->default(false);
            $table->string('locale', 5)->default('fa');
            $table->char('currency_code', 3)->default('AFN');
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();                  // یادداشت مدیر
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('orders_total', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
