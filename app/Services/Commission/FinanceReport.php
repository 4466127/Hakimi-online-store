<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\CommissionEntry;
use App\Models\Payment;
use App\Models\SellerSettlement;
use App\Models\SellerTransaction;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * عددهای صفحه‌ی مالی — همه از ردیف‌های واقعی دیتابیس، هیچ عدد ساختگی.
 *
 * تعریف‌ها (docs/COMMISSION_AND_SELLER_SETTLEMENT.md بخش ۹):
 * - «فروش معتبر» = سندهای کمیسیون (فقط خط‌های تحویل‌شده و پول‌رسیده؛ لغوشده‌ها هیچ سندی ندارند)، منهای برگشت‌ها.
 *   هر خط سفارش فقط یک سند فروش دارد (کلید یکتا)، پس هیچ فروشی دو بار شمرده نمی‌شود.
 * - «فروش خود بازارک» (بی فروشنده) جدا از «فروش فروشندگان» (GMV بازارگاه)؛ درآمد بازارک از فروشندگان فقط «کمیسیون» است.
 * - «سهم فروشندگان» = طلب ثبت‌شده؛ «تسویه‌شده» = پولی که واقعاً به فروشنده داده شد. این دو یکی نیستند.
 * - «پول گرفته‌شده از مشتری» (پرداخت‌های paid) جدا از «پولی که پیک به صندوق داد» (تحویل پول، پرداخت دوم نیست).
 * - مانده‌ها (منتظر، قابل تسویه، طلب باقی، پول دست پیک، پرداخت منتظر مشتری) «همین حالا» هستند، نه در بازه.
 */
class FinanceReport
{
    public const PERIODS = ['today', 'week', 'month', 'year', 'all'];

    /**
     * @return array{0: ?Carbon, 1: ?Carbon} [از، تا) — هفته از شنبه (افغانستان)
     */
    public static function range(string $period, ?Carbon $now = null): array
    {
        $now ??= now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->addDay()->startOfDay()],
            'week' => [$now->copy()->startOfWeek(Carbon::SATURDAY), $now->copy()->startOfWeek(Carbon::SATURDAY)->addWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->startOfMonth()->addMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->startOfYear()->addYear()],
            default => [null, null],
        };
    }

    /**
     * @return array<string, string>
     */
    public function totals(?Carbon $from = null, ?Carbon $to = null): array
    {
        $inRange = function ($q, string $column) use ($from, $to) {
            return $q->when($from, fn ($q) => $q->where($column, '>=', $from))
                ->when($to, fn ($q) => $q->where($column, '<', $to));
        };

        $entries = fn () => $inRange(CommissionEntry::query(), 'occurred_at');
        $sum = fn ($q, string $col): string => Money::of((string) $q->sum($col));

        $paid = fn () => $inRange(Payment::query()->where('status', Payment::PAID), 'paid_at');

        return [
            // فروش (در بازه)
            'valid_sales' => $sum($entries(), 'gross_amount'),
            'own_sales' => $sum($entries()->whereNull('seller_id'), 'gross_amount'),
            'marketplace_gmv' => $sum($entries()->whereNotNull('seller_id'), 'gross_amount'),
            'commission' => $sum($entries(), 'commission_amount'),
            'seller_earnings' => $sum($entries(), 'seller_amount'),
            'returns' => Money::sub(0, $sum($entries()->where('kind', CommissionEntry::KIND_REVERSAL), 'gross_amount')),
            'settled' => $sum($inRange(SellerSettlement::query(), 'paid_at'), 'amount'),
            // پول مشتری و پیک (در بازه)
            'cash_collected' => $sum($paid()->whereNotNull('collected_at_type'), 'amount_received'),
            'cash_remitted' => $sum($inRange(Payment::query()->where('courier_cash_status', Payment::HANDED_OVER), 'handed_over_at'), 'amount_received'),
            'gateway_fees' => $sum($paid(), 'fee_amount'),
            // مانده‌ها (همین حالا)
            'cash_held' => $sum(Payment::query()->where('status', Payment::PAID)->where('courier_cash_status', Payment::HELD_BY_COURIER), 'amount_received'),
            'pending_customer_payments' => $sum(Payment::query()->whereIn('status', [Payment::AWAITING_COLLECTION, Payment::PENDING])
                ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'returned'])), 'amount'),
            'seller_pending' => $sum(SellerTransaction::query()->pending(), 'open_amount'),
            'seller_eligible' => $sum(SellerTransaction::query()->eligible(), 'open_amount'),
            'seller_outstanding' => $sum(SellerTransaction::query()->whereIn('id', SellerTransaction::query()->selectRaw('MAX(id)')->groupBy('seller_id')), 'balance_after'),
        ];
    }
}
