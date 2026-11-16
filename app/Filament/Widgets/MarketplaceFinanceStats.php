<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Support\Num;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * خلاصه‌ی مالی بازارگاه (داشبورد و «سوابق کمیسیون»): فروش فروشندگان، کمیسیون بازارک،
 * سهم فروشندگان، فروش خود بازارک (جدا)، تسویه‌شده، طلب باقی فروشندگان. فقط صاحب و مدیر.
 */
class MarketplaceFinanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canManageSettings();
    }

    protected function getStats(): array
    {
        $t = app(CommissionService::class)->totals();
        $afn = ' '.__('admin.finance.afn');
        $money = fn (string $amount): string => Num::format($amount, 0).$afn;

        return [
            Stat::make(__('admin.finance.stats_marketplace_sales'), $money($t['marketplace_sales']))->color('gray'),
            Stat::make(__('admin.finance.stats_commission'), $money($t['commission']))->color('success'),
            Stat::make(__('admin.finance.stats_seller_earnings'), $money($t['seller_earnings'])),
            Stat::make(__('admin.finance.stats_own_sales'), $money($t['own_store_sales']))->color('primary'),
            Stat::make(__('admin.finance.stats_settled'), $money($t['settled'])),
            Stat::make(__('admin.finance.stats_outstanding'), $money($t['outstanding']))->color('warning'),
        ];
    }
}
