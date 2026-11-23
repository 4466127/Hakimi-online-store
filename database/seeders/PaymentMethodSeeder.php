<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CollectionPoint;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * روش‌های پرداخت MVP و «دفتر مرکزی» (اولین نمایندگی).
 *
 * - cod: پول هنگام تحویل به پیک (فعال).
 * - cash_point: نقد در نمایندگی، همراه تحویل حضوری (فعال).
 * - hesabpay: آنلاین؛ **غیرفعال** تا درگاه واقعاً از روی مستندات رسمی ساخته و آزمایش شود (مرحله‌ی ۵).
 *
 * چند بار اجرا کردنش بی‌خطر است و هر چه مدیر در پنل عوض کرده نگه می‌دارد (فقط ردیف نبودنی ساخته می‌شود).
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => PaymentMethod::COD, 'type' => 'cod', 'is_active' => true, 'position' => 1,
                'name' => ['fa' => 'پرداخت هنگام تحویل'],
                'description' => ['fa' => 'پول را هنگام رسیدن جنس به پیک می‌دهید.'],
                'instructions' => ['fa' => 'لطفاً مبلغ سفارش را هنگام تحویل آماده داشته باشید. پیک بعد از گرفتن پول رسید می‌دهد.'],
            ],
            [
                'code' => PaymentMethod::CASH_POINT, 'type' => 'cash_point', 'is_active' => true, 'position' => 2,
                'name' => ['fa' => 'پرداخت نقد در نمایندگی'],
                'description' => ['fa' => 'جنس را خودتان از نمایندگی می‌گیرید و همان‌جا پول می‌دهید.'],
                'instructions' => ['fa' => 'با شماره‌ی سفارش به نمایندگی بیایید و هنگام گرفتن جنس پول را بدهید.'],
            ],
            [
                'code' => PaymentMethod::HESABPAY, 'type' => 'gateway', 'driver' => 'hesabpay', 'is_active' => false, 'position' => 3,
                'name' => ['fa' => 'پرداخت آنلاین با حساب‌پی'],
                'description' => ['fa' => 'به‌زودی.'],
            ],
        ];

        foreach ($methods as $row) {
            PaymentMethod::query()->firstOrCreate(['code' => $row['code']], $row);
        }

        if (! CollectionPoint::query()->exists()) {
            CollectionPoint::query()->create([
                'name' => ['fa' => 'دفتر مرکزی'],
                'type' => 'office',
                'is_active' => true,
                'position' => 1,
            ]);
        }
    }
}
