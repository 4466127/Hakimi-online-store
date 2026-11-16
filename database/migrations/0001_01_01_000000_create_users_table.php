<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کاربران پنل مدیریت (اعضای خانواده) + جدول‌های استاندارد لاراول برای ورود.
 * مشتریان فروشگاه جدول جدا دارند (customers، مرحله‌ی ۴).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('phone', 20)->unique();          // نرمال‌شده: 07XXXXXXXX
            $table->string('email', 150)->nullable()->unique();
            $table->string('password');
            $table->string('role', 20)->default('staff');   // owner / manager / staff / editor
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failed_logins')->default(0);
            $table->dateTime('locked_until')->nullable();   // قفل بعد از چند رمز اشتباه
            $table->dateTime('password_changed_at')->nullable(); // خالی = باید رمز اولیه را عوض کند
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
