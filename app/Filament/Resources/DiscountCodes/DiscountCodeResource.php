<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes;

use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Filament\Resources\DiscountCodes\RelationManagers\RedemptionsRelationManager;
use App\Filament\Resources\DiscountCodes\Schemas\DiscountCodeForm;
use App\Filament\Resources\DiscountCodes\Tables\DiscountCodesTable;
use App\Models\DiscountCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * «مدیریت کدهای تخفیف» (مرحله‌ی ۹-۳). تخفیف از جیب بازارک است؛ سهم فروشنده کم نمی‌شود.
 */
class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.discounts.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.discounts.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.discounts.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [RedemptionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscountCodes::route('/'),
            'create' => CreateDiscountCode::route('/create'),
            'edit' => EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
