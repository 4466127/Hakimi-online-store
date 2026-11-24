<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payments\Gateways\HesabPayGateway;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * پیام‌گیر سرور-به-سرور حساب‌پی: POST /webhooks/hesabpay (بی CSRF؛ اصالت با امضا).
 *
 * طبق مستندات (Webhooks Overview):
 * - اول امضا و timestamp نزد خود حساب‌پی بررسی می‌شود؛ بی امضای درست هیچ سفارشی عوض نمی‌شود (۴۰۱).
 * - آزمون پورتال (`webhook.validation`) فقط `{"success": true}` می‌گیرد و هیچ سفارشی را عوض نمی‌کند.
 * - پیام پرداخت بعد از پذیرفتن HTTP 200 می‌گیرد؛ پیام تکراری هم ۲۰۰ (بی اثر). خطای سرور ما = ۵۰۰ تا حساب‌پی دوباره بفرستد.
 */
class HesabPayWebhookController extends Controller
{
    public function __invoke(Request $request, HesabPayGateway $gateway, OnlinePaymentService $online): JsonResponse
    {
        if (! $gateway->isReady()) {
            return response()->json(['success' => false, 'message' => 'not configured'], 503);
        }

        try {
            $event = $gateway->verify($request->getContent(), []);
        } catch (PaymentException $e) {
            Log::warning('payment.hesabpay.webhook_rejected', ['reason' => $e->reason, 'ip' => $request->ip()]);
            $status = $e->reason === 'gateway_unavailable' ? 503 : ($e->reason === 'invalid_payload' ? 400 : 401);

            return response()->json(['success' => false, 'message' => $e->reason], $status);
        }

        if ($event->isValidation()) {
            return response()->json(['success' => true]);
        }

        $record = $online->handle(HesabPayGateway::CODE, $event);

        return response()->json(['success' => true, 'outcome' => $record->outcome]);
    }
}
