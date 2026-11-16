<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements;

use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Resources\Settlements\Tables\SettlementsTable;
use App\Models\SellerSettlement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * همه‌ی تسویه‌ها با فروشندگان (فقط دیدن؛ ثبت از صفحه‌ی فروشنده).
 */
class SettlementResource extends Resource
{
    protected static ?string $model = SellerSettlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'settlements';

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.marketplace');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.finance.settlements');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.finance.settlements');
    }

    public static function table(Table $table): Table
    {
        return SettlementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettlements::route('/'),
        ];
    }
}
