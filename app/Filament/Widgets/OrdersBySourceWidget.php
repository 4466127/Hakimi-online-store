<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use App\Support\Num;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * «سفارش‌ها به منبع تبلیغ» در داشبورد (مرحله‌ی ۹-۲): تعداد و مبلغ سفارش‌های هر utm_source،
 * در ۳۰ روز اخیر و از اول. سفارش لغوشده و برگشتی حساب نمی‌شود. فقط صاحب و مدیر.
 */
class OrdersBySourceWidget extends Widget
{
    protected string $view = 'filament.widgets.orders-by-source';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canManageSettings();
    }

    /**
     * @return list<array{source: string, recent_count: string, recent_total: string, all_count: string, all_total: string}>
     */
    public static function rows(): array
    {
        $since = now()->subDays(30);
        $data = Order::query()
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->groupBy('utm_source')
            ->select('utm_source')
            ->selectRaw('COUNT(*) as all_count, SUM(total) as all_total')
            ->selectRaw('SUM(CASE WHEN placed_at >= ? THEN 1 ELSE 0 END) as recent_count', [$since])
            ->selectRaw('SUM(CASE WHEN placed_at >= ? THEN total ELSE 0 END) as recent_total', [$since])
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->toBase()
            ->get();

        return $data->map(fn (object $row): array => [
            'source' => $row->utm_source ?? (string) __('admin.campaigns.direct'),
            'recent_count' => Num::toFa((string) (int) $row->recent_count),
            'recent_total' => Money::format(Money::of((string) ($row->recent_total ?? '0'))),
            'all_count' => Num::toFa((string) (int) $row->all_count),
            'all_total' => Money::format(Money::of((string) ($row->all_total ?? '0'))),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['rows' => self::rows()];
    }
}
