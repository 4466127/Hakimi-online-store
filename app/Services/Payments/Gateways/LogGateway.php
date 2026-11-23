<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentException;
use Illuminate\Support\Facades\Log;

/**
 * درایور توسعه: هیچ پولی جابه‌جا نمی‌کند.
 *
 * تا درگاه واقعی (حساب‌پی) با حساب پذیرنده و کلید API و مستندات رسمی ساخته نشود، همین درایور
 * کار می‌کند: درخواست را در لاگ می‌نویسد و به کاربر می‌گوید «پرداخت آنلاین هنوز آماده نیست».
 * **هیچ‌وقت** پرداختی را «پرداخت‌شده» نمی‌کند (قاعده‌ی پروژه: درگاه ساختگی ممنوع).
 */
class LogGateway implements PaymentGateway
{
    public function __construct(private readonly string $code = 'log') {}

    public function code(): string
    {
        return $this->code;
    }

    public function isReady(): bool
    {
        return false;
    }

    public function start(Payment $payment, Order $order): GatewayStart
    {
        Log::info('payment.gateway.not_ready', [
            'driver' => $this->code,
            'order' => $order->number,
            'payment' => $payment->uuid,
            'amount' => $payment->amount,
        ]);

        throw new PaymentException('gateway_not_ready');
    }

    public function verify(string $payload, array $headers): GatewayEvent
    {
        Log::warning('payment.gateway.unexpected_webhook', ['driver' => $this->code, 'length' => strlen($payload)]);

        throw new PaymentException('gateway_not_ready');
    }
}
