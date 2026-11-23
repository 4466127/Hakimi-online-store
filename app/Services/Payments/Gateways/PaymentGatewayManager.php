<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Models\PaymentMethod;

/**
 * درگاه هر روش پرداخت را پیدا می‌کند.
 *
 * نام درایور در ستون `payment_methods.driver` است و فهرست درایورهای شناخته‌شده در
 * `config/store.php` (`payments.drivers`). هر درایور ناشناخته یا نیمه‌کاره ← `LogGateway`،
 * یعنی «آماده نیست»؛ پس هیچ‌وقت سفارشی با درگاه ناموجود ثبت نمی‌شود.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function driver(?string $name): PaymentGateway
    {
        $name = $name === null || trim($name) === '' ? 'log' : trim($name);

        if (! isset($this->resolved[$name])) {
            /** @var array<string, class-string<PaymentGateway>> $drivers */
            $drivers = (array) config('store.payments.drivers', []);
            $class = $drivers[$name] ?? null;

            $this->resolved[$name] = $class !== null && class_exists($class)
                ? app($class)
                : new LogGateway($name);
        }

        return $this->resolved[$name];
    }

    public function for(PaymentMethod $method): PaymentGateway
    {
        return $this->driver($method->driver);
    }

    /** این روش پرداخت آنلاین واقعاً آماده‌ی استفاده است؟ (روش‌های نقد همیشه آماده‌اند) */
    public function isReady(PaymentMethod $method): bool
    {
        return $method->isCash() || $this->for($method)->isReady();
    }
}
