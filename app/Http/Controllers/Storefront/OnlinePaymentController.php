<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * صفحه‌های مشتری برای پرداخت آنلاین (حساب‌پی).
 *
 * - آدرس‌ها با uuid سفارش/پرداخت است (حدس‌زدنی نیست؛ مثل صفحه‌ی سفارش).
 * - **برگشت مرورگر از حساب‌پی هیچ وضعیتی را عوض نمی‌کند**؛ فقط پیام مناسب نشان می‌دهد.
 *   پرداخت‌شده فقط با پیام امضاشده‌ی سرور-به-سرور (HesabPayWebhookController).
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private readonly OnlinePaymentService $online) {}

    /** رفتن (دوباره) به صفحه‌ی پرداخت حساب‌پی. */
    public function pay(string $uuid): RedirectResponse
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();
        $payment = $this->online->pendingPayment($order);
        if ($payment === null) {
            return redirect()->route('order.show', ['uuid' => $order->uuid]);
        }

        try {
            return redirect()->away($this->online->begin($payment));
        } catch (PaymentException $e) {
            return redirect()->route('order.show', ['uuid' => $order->uuid])->with('error', $e->userMessage());
        }
    }

    /** به‌جای آنلاین، نقد (پیک یا نمایندگی). */
    public function switchToCash(string $uuid): RedirectResponse
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $this->online->switchToCash($order);
        } catch (PaymentException $e) {
            return redirect()->route('order.show', ['uuid' => $order->uuid])->with('error', $e->userMessage());
        }

        return redirect()->route('order.show', ['uuid' => $order->uuid])->with('status', __('payments.online.switched'));
    }

    /** برگشت «موفق» از حساب‌پی: فقط پیام «در حال تأیید» (یا «تأیید شد» اگر پیام سرور زودتر رسیده). */
    public function returned(string $payment): RedirectResponse
    {
        $row = Payment::query()->where('uuid', $payment)->with('order')->firstOrFail();

        return redirect()->route('order.show', ['uuid' => $row->order->uuid])
            ->with('status', __($row->status === Payment::PAID ? 'payments.online.confirmed' : 'payments.online.returned'));
    }

    /**
     * برگشت «ناموفق/لغو/نامعلوم» از حساب‌پی. پارامتر `data` (JSON) را مشتری می‌تواند عوض کند؛
     * پس فقط برای نمایش متن است (به‌شکل متن ساده، با escape در صفحه) و هیچ‌چیز را عوض نمی‌کند.
     */
    public function failed(Request $request, string $payment): RedirectResponse
    {
        $row = Payment::query()->where('uuid', $payment)->with('order')->firstOrFail();
        $to = redirect()->route('order.show', ['uuid' => $row->order->uuid]);
        if ($row->status === Payment::PAID) {
            return $to->with('status', __('payments.online.confirmed'));
        }

        $raw = (string) $request->query('data', '');
        $result = strlen($raw) > 0 && strlen($raw) <= 8192 ? json_decode($raw, true) : null;
        $valid = is_array($result)
            && ($result['success'] ?? null) === false
            && in_array($result['status'] ?? null, ['failed', 'cancelled', 'pending'], true)
            && is_string($result['message'] ?? null);

        if ($valid && $result['status'] === 'pending') {
            return $to->with('error', __('payments.online.pending_unknown'));
        }
        if ($valid && trim($result['message']) !== '') {
            return $to->with('error', __('payments.online.failed', ['message' => mb_substr(trim(strip_tags($result['message'])), 0, 200)]));
        }

        return $to->with('error', __('payments.online.failed_generic'));
    }
}
