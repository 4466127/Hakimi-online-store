<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Commission\FinanceReport;
use App\Support\Num;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Locked;

/**
 * خلاصه‌ی مالی (داشبورد و «سوابق کمیسیون»)، فقط صاحب و مدیر. همه از FinanceReport (ردیف‌های واقعی).
 *
 * با دکمه‌های امروز / این هفته / این ماه / امسال / همه. عددهای «فروش و پول» در همان بازه‌اند؛
 * «مانده‌ها» (طلب منتظر و قابل تسویه، پول دست پیک، پرداخت منتظر مشتری) همیشه «همین حالا» هستند.
 */
class MarketplaceFinanceStats extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected static ?int $sort = 5;

    #[Locked]
    public string $period = 'month';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canManageSettings();
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, FinanceReport::PERIODS, true) ? $period : 'month';
        $this->cachedStats = null;
    }

    protected function getHeading(): ?string
    {
        return (string) __('admin.finance.report_heading', ['period' => __('admin.finance.periods.'.$this->period)]);
    }

    protected function getDescription(): ?string
    {
        return (string) __('admin.finance.report_help');
    }

    public function getSectionContentComponent(): Component
    {
        return parent::getSectionContentComponent()->afterHeader(
            collect(FinanceReport::PERIODS)->map(fn (string $p): Action => Action::make('period_'.$p)
                ->label(__('admin.finance.periods.'.$p))
                ->link()
                ->color($this->period === $p ? 'primary' : 'gray')
                ->action(fn () => $this->setPeriod($p)))->all(),
        );
    }

    protected function getColumns(): int|array|null
    {
        return ['default' => 1, 'sm' => 2, 'lg' => 4];
    }

    protected function getStats(): array
    {
        [$from, $to] = FinanceReport::range($this->period);
        $t = app(FinanceReport::class)->totals($from, $to);
        $afn = ' '.__('admin.finance.afn');
        $money = fn (string $amount): string => Num::format($amount, 0).$afn;
        $stat = fn (string $key, string $color = 'gray'): Stat => Stat::make(__('admin.finance.stats.'.$key), $money($t[$key]))
            ->description(__('admin.finance.stats_help.'.$key))
            ->color($color);

        return [
            // فروش
            $stat('valid_sales'),
            $stat('own_sales', 'primary'),
            $stat('marketplace_gmv'),
            $stat('commission', 'success'),
            // فروشندگان
            $stat('seller_earnings'),
            $stat('seller_eligible', 'success'),
            $stat('seller_pending', 'info'),
            $stat('seller_outstanding', 'warning'),
            $stat('settled'),
            $stat('returns', 'danger'),
            // پول مشتری و پیک
            $stat('cash_collected'),
            $stat('cash_remitted'),
            $stat('cash_held', 'warning'),
            $stat('pending_customer_payments', 'info'),
            $stat('gateway_fees'),
        ];
    }
}
