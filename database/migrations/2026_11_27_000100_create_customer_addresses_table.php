<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفترچه‌ی آدرس مشتری (۶ قوس ۱۴۰۵، خواست صاحب پروژه): آدرسی که مشتری عضو یک بار در تسویه نوشت،
 * بعد از ثبت سفارش نگه داشته می‌شود تا بار دوم با یک لمس انتخاب کند.
 *
 * - «پیش‌فرض» ستون جدا ندارد: آدرسی که آخر استفاده شده (last_used_at) اول می‌آید و خودکار انتخاب می‌شود.
 * - هر مشتری حداکثر ۱۰ آدرس (CustomerAddressBook::MAX)؛ مشتری در «حساب من» آدرس را پاک می‌کند.
 * - فقط جدول تازه؛ هیچ داده‌ای پاک نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->text('address_line');
            $table->text('delivery_instructions')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->string('delivery_location_source', 10)->nullable();   // map | gps
            $table->dateTime('last_used_at');
            $table->timestamps();

            $table->index(['customer_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
