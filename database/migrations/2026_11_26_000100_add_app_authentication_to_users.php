<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ورود دومرحله‌ای پنل مدیریت (مرحله‌ی ۸، امنیت پیش از پرداخت آنلاین).
 * کلید برنامه‌ی کد (Google Authenticator و مانند آن) و کدهای پشتیبان، هر دو رمزنگاری‌شده با APP_KEY.
 * کدهای پشتیبان خودشان هم hash شده‌اند (Filament)؛ هیچ‌کدام در لاگ نوشته نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
