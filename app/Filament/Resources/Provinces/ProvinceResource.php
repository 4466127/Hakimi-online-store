<?php

declare(strict_types=1);

namespace App\Filament\Resources\Provinces;

use App\Filament\Resources\Provinces\Pages\ListProvinces;
use App\Models\Province;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * ولایت‌ها و اینکه فعلاً به کدام‌ها جنس می‌رسانیم.
 * با یک کلیک روی «تحویل فعال» ولایت تازه باز می‌شود؛ بقیه در سایت «زیر کار است» نشان داده می‌شود.
 * (کرایه و منطقه‌های هر ولایت در مرحله‌ی ۵ اضافه می‌شود.)
 */
class ProvinceResource extends Resource
{
    protected static ?string $model = Province::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.system');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.provinces.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.provinces.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.provinces.help'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.provinces.name'))
                    ->state(fn (Province $record): string => $record->tr('name')),
                ToggleColumn::make('delivery_enabled')
                    ->label(__('admin.provinces.delivery_enabled')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.provinces.is_active')),
            ])
            ->filters([
                TernaryFilter::make('delivery_enabled')
                    ->label(__('admin.provinces.delivery_enabled')),
            ])
            ->defaultSort('position')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvinces::route('/'),
        ];
    }
}
