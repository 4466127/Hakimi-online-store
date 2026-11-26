<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * درگاه حساب‌پی (HesabPay) — **فقط از روی مستندات رسمی** (docs.hesab.com، چاپ ۲۴ نوامبر ۲۰۲۶ در
 * resources/hesabpay/؛ خلاصه در docs/hesabpay.md).
 *
 * - کلید: هدر `Authorization: API-KEY <key>`؛ فقط از `.env` (config services.hesabpay).
 * - ساختن نشست: POST /api/v1/payment/create-session → { session_id, url, expires_at } (۱۵ دقیقه).
 *   `items` بی تعداد است؛ ما یک قلم «سفارش …» با مبلغ نهایی می‌فرستیم تا جمع دقیقاً همان مبلغ سفارش
 *   باشد (تخفیف و کرایه هم داخلش). `user_id` = uuid پرداخت ما (حداکثر ۵۰ حرف).
 * - پیام سرور-به-سرور: امضا و timestamp داخل خود JSON است و **فقط** با
 *   POST /api/v1/hesab/webhooks/verify-signature نزد خود حساب‌پی بررسی می‌شود.
 * - بررسی تراکنش برای تطبیق: GET /api/v1/hesab/get/transaction-detail/{id}
 *   (`success: true` یعنی «پیدا شد»، نه «پرداخت شد»).
 * - بازپرداخت API رمز (PIN) کیف‌پول را می‌خواهد و جزئی ندارد؛ ساخته نشد و فعلاً از داشبورد حساب‌پی است.
 */
class HesabPayGateway implements PaymentGateway
{
    public const CODE = 'hesabpay';

    /** کمترین پرداخت کیف‌پول حساب‌پی؛ بیشترین جمع اقلام یک نشست. */
    public const MIN_AMOUNT = '10.00';

    public const MAX_AMOUNT = '999999.99';

    public function code(): string
    {
        return self::CODE;
    }

    public function isReady(): bool
    {
        return $this->apiKey() !== '' && $this->baseUrl() !== '';
    }

    public function isSandbox(): bool
    {
        return config('services.hesabpay.env') !== 'production';
    }

    public function start(Payment $payment, Order $order): GatewayStart
    {
        if (! $this->isReady()) {
            throw new PaymentException('gateway_not_ready');
        }
        $amount = Money::of($payment->amount);
        if (Money::compare($amount, self::MIN_AMOUNT) < 0 || Money::compare($amount, self::MAX_AMOUNT) > 0) {
            throw new PaymentException('gateway_amount_out_of_range');
        }

        $body = array_filter([
            'email' => $order->purchaser_email ?: null,
            'user_id' => $payment->uuid,
            'items' => [[
                'id' => (string) $order->number,
                'name' => mb_substr((string) __('payments.online.item_name', ['number' => $order->number, 'count' => $order->items()->count()]), 0, 500),
                'price' => (float) $amount,
            ]],
            'redirect_success_url' => route('payment.hesabpay.return', ['payment' => $payment->uuid]),
            'redirect_failure_url' => route('payment.hesabpay.failed', ['payment' => $payment->uuid]),
        ], fn ($value) => $value !== null);

        $response = $this->send(fn (PendingRequest $http) => $http->post('/api/v1/payment/create-session', $body), 'create_session', $order);
        $data = (array) $response->json();

        if (! $response->successful() || ($data['success'] ?? false) !== true || ! is_string($data['url'] ?? null) || ! is_string($data['session_id'] ?? null)) {
            Log::warning('payment.hesabpay.session_failed', [
                'order' => $order->number,
                'http' => $response->status(),
                // متن پیام خطا بی کلید و بی امضاست
                'message' => is_string($data['message'] ?? null) ? mb_substr($data['message'], 0, 200) : ($data['detail'] ?? null),
            ]);

            throw new PaymentException($response->status() === 403 ? 'gateway_domain_not_verified' : 'gateway_error');
        }

        // مشتری فقط به صفحه‌ی امن (https) حساب‌پی فرستاده شود؛ اگر نام سایت پرداخت در .env آمده، فقط همان
        if (! $this->isAllowedCheckoutUrl($data['url'])) {
            Log::warning('payment.hesabpay.unexpected_checkout_url', [
                'order' => $order->number,
                'host' => mb_substr((string) parse_url($data['url'], PHP_URL_HOST), 0, 100),
            ]);

            throw new PaymentException('gateway_error');
        }

        $expires = null;
        try {
            $expires = isset($data['expires_at']) ? CarbonImmutable::parse((string) $data['expires_at']) : null;
        } catch (Throwable) {
            $expires = null;
        }

        return new GatewayStart($data['url'], $data['session_id'], $expires ?? CarbonImmutable::now()->addMinutes(15));
    }

    /**
     * آدرس صفحه‌ی پرداخت: فقط https، بی نام کاربری و رمز در آدرس، و اگر HESABPAY_CHECKOUT_HOSTS پر است
     * نام سایت باید دقیقاً یکی از آن‌ها یا زیردامنه‌ی یکی از آن‌ها باشد (جلوی فرستادن مشتری به سایت جعلی).
     */
    public static function isAllowedCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $allowed = (array) config('services.hesabpay.checkout_hosts', []);
        if ($allowed === []) {
            return true;
        }

        $host = strtolower($parts['host']);
        foreach ($allowed as $candidate) {
            $candidate = strtolower(ltrim((string) $candidate, '.'));
            if ($candidate !== '' && ($host === $candidate || str_ends_with($host, '.'.$candidate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * پیام حساب‌پی: امضا و timestamp را نزد خود حساب‌پی بررسی می‌کند، بعد پیام را می‌خواند.
     *
     * @throws PaymentException invalid_payload | invalid_signature | gateway_unavailable
     */
    public function verify(string $payload, array $headers): GatewayEvent
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            throw new PaymentException('invalid_payload');
        }
        $signature = $data['signature'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        if (! is_scalar($signature) || ! is_scalar($timestamp) || (string) $signature === '' || (string) $timestamp === '') {
            throw new PaymentException('invalid_signature');
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post('/api/v1/hesab/webhooks/verify-signature', [
            'signature' => (string) $signature,
            'timestamp' => (string) $timestamp,
        ]), 'verify_signature');
        if ($response->serverError()) {
            throw new PaymentException('gateway_unavailable');   // حساب‌پی دوباره می‌فرستد
        }
        if (! $response->successful() || $response->json('success') !== true) {
            throw new PaymentException('invalid_signature');
        }

        unset($data['signature'], $data['timestamp']);
        $str = fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '' ? (string) $data[$key] : null;

        // آزمون پورتال («آدرس کار می‌کند؟») — هیچ مبلغ و سفارشی ندارد
        if (($data['type'] ?? null) === 'webhook.validation') {
            return new GatewayEvent('validation:'.($str('validation_id') ?? 'x'), '', GatewayEvent::VALIDATION, raw: $data);
        }

        $amount = isset($data['amount']) && is_numeric($data['amount']) ? Money::of((string) $data['amount']) : null;
        $transactionId = $str('transaction_id');
        $sessionId = $str('session_id');
        $reference = mb_substr($str('user_id') ?? '', 0, 64);

        if (($data['success'] ?? null) === true) {
            if ($transactionId === null) {
                throw new PaymentException('invalid_payload');
            }

            return new GatewayEvent('txn:'.$transactionId, $reference, GatewayEvent::PAID, $amount, 'AFN', $data, $transactionId, $sessionId);
        }

        $reason = mb_substr($str('reason') ?? 'payment_failed', 0, 30);
        // ردِ بی‌تراکنش: کلید از نشست/مرجع و دلیل (نه ساختن شناسه‌ی تراکنش از user_id)
        $eventId = $transactionId !== null
            ? 'fail:'.$transactionId
            : 'fail:'.($sessionId ?? $reference).':'.($str('failure_stage') ?? '').':'.$reason;

        return new GatewayEvent(mb_substr($eventId, 0, 120), $reference, GatewayEvent::FAILED, $amount, 'AFN', $data, $transactionId, $sessionId, $reason);
    }

    /**
     * بررسی یک تراکنش نزد حساب‌پی (برای تطبیق مدیر). هیچ وضعیتی را عوض نمی‌کند.
     *
     * @return array{found: bool, http: int, data: array<string, mixed>}
     */
    public function lookup(string $transactionId): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/api/v1/hesab/get/transaction-detail/'.rawurlencode($transactionId)), 'lookup');

        return [
            'found' => $response->successful() && $response->json('success') === true,
            'http' => $response->status(),
            'data' => (array) ($response->json('data') ?? []),
        ];
    }

    /** @param callable(PendingRequest): Response $call */
    private function send(callable $call, string $what, ?Order $order = null): Response
    {
        try {
            return $call(Http::baseUrl($this->baseUrl())
                ->withHeaders(['Authorization' => 'API-KEY '.$this->apiKey()])
                ->acceptJson()
                ->asJson()
                ->timeout(max(5, (int) config('services.hesabpay.timeout', 20))));
        } catch (ConnectionException $e) {
            Log::warning('payment.hesabpay.connection_failed', ['call' => $what, 'order' => $order?->number, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            throw new PaymentException('gateway_unavailable');
        }
    }

    private function apiKey(): string
    {
        return trim((string) config('services.hesabpay.api_key'));
    }

    private function baseUrl(): string
    {
        $url = trim((string) config('services.hesabpay.api_url'));
        if ($url === '') {
            $url = $this->isSandbox() ? 'https://api-sandbox.hesab.com' : 'https://api.hesab.com';
        }

        return rtrim($url, '/');
    }
}
