<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionEntries;

use App\Filament\Resources\CommissionEntries\Pages\ListCommissionEntries;
use App\Models\CommissionEntry;
use App\Models\Seller;
use App\Support\Jalali;
use App\Support\Num;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * سوابق کمیسیون: هر فروش تحویل‌شده و هر برگشت، با نرخ و مبلغ همان روز. فقط دیدن.
 */
class CommissionEntryResource extends Resource
{
    protected static ?string $model = CommissionEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'commissions';

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.marketplace');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.finance.entries');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.finance.entries');
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => Num::format((string) $state, 2);

        return $table
            ->description(__('admin.finance.entries_help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['seller', 'category']))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('admin.finance.occurred_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->sortable(),
                TextColumn::make('seller.store_name')
                    ->label(__('admin.finance.seller'))
                    ->placeholder(__('admin.finance.own_store'))
                    ->searchable(),
                TextColumn::make('kind')
                    ->label(__('admin.finance.kind'))
                    ->formatStateUsing(fn (string $state): string => (string) __('commission.kinds.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sale' ? 'success' : 'danger'),
                TextColumn::make('category')
                    ->label(__('admin.finance.rate_category'))
                    ->state(fn (CommissionEntry $record): ?string => $record->category?->tr('name'))
                    ->placeholder('—'),
                TextColumn::make('gross_amount')->label(__('admin.finance.gross'))->formatStateUsing($money),
                TextColumn::make('commission_rate')
                    ->label(__('admin.finance.commission_rate'))
                    ->formatStateUsing(fn ($state): string => Num::format((string) $state, 2).'٪'),
                TextColumn::make('commission_amount')->label(__('admin.finance.commission_amount'))->formatStateUsing($money)->weight('bold'),
                TextColumn::make('seller_amount')->label(__('admin.finance.seller_amount'))->formatStateUsing($money),
                TextColumn::make('rate_source')
                    ->label(__('admin.finance.rate_source'))
                    ->formatStateUsing(fn (CommissionEntry $record): string => $record->rateSourceLabel())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->label(__('admin.finance.description'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->label(__('admin.finance.filter_scope'))
                    ->options([
                        'marketplace' => __('admin.finance.scope_marketplace'),
                        'own' => __('admin.finance.scope_own'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'marketplace' => $query->whereNotNull('seller_id'),
                        'own' => $query->whereNull('seller_id'),
                        default => $query,
                    }),
                SelectFilter::make('seller_id')
                    ->label(__('admin.finance.seller'))
                    ->options(fn (): array => Seller::query()->whereNotNull('store_name')->orderBy('store_name')->pluck('store_name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('kind')
                    ->label(__('admin.finance.kind'))
                    ->options((array) __('commission.kinds')),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('admin.finance.empty_entries'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionEntries::route('/'),
        ];
    }
}
