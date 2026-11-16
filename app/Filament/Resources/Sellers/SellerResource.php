<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers;

use App\Enums\SellerStatus;
use App\Filament\Resources\Sellers\Pages\ListSellers;
use App\Filament\Resources\Sellers\Pages\ViewSeller;
use App\Filament\Resources\Sellers\RelationManagers\CommissionRatesRelationManager;
use App\Filament\Resources\Sellers\RelationManagers\SettlementsRelationManager;
use App\Filament\Resources\Sellers\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Sellers\Schemas\SellerInfolist;
use App\Filament\Resources\Sellers\Tables\SellersTable;
use App\Models\Seller;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Throwable;

/**
 * فروشندگان («در بازارک بفروشید»): دیدن درخواست‌ها، تأیید، رد و بستن.
 */
class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.marketplace');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.sellers.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.sellers.plural');
    }

    /** تعداد درخواست‌های «منتظر بررسی» کنار نام منو. */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = Seller::query()->where('status', SellerStatus::Pending->value)->count();
        } catch (Throwable $e) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return SellerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CommissionRatesRelationManager::class,
            TransactionsRelationManager::class,
            SettlementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellers::route('/'),
            'view' => ViewSeller::route('/{record}'),
        ];
    }
}
