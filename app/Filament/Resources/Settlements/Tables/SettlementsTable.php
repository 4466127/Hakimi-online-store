<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settlements\Tables;

use App\Models\SellerSettlement;
use App\Support\Jalali;
use App\Support\Num;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * جدول تسویه‌ها (در لیست «تسویه‌ها» و در صفحه‌ی هر فروشنده).
 */
class SettlementsTable
{
    public static function configure(Table $table, bool $showSeller = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['seller', 'creator']))
            ->columns(array_values(array_filter([
                TextColumn::make('number')
                    ->label(__('admin.finance.number'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->label(__('admin.finance.paid_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::date($state))
                    ->sortable(),
                $showSeller ? TextColumn::make('seller.store_name')
                    ->label(__('admin.finance.seller'))
                    ->searchable() : null,
                TextColumn::make('amount')
                    ->label(__('admin.finance.amount'))
                    ->formatStateUsing(fn ($state): string => Num::format((string) $state, 2))
                    ->weight('bold'),
                TextColumn::make('method')
                    ->label(__('admin.finance.method'))
                    ->formatStateUsing(fn (SellerSettlement $record): string => $record->methodLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reference')
                    ->label(__('admin.finance.reference'))
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('period')
                    ->label(__('admin.finance.period_from').' / '.__('admin.finance.period_to'))
                    ->state(fn (SellerSettlement $record): ?string => $record->period_from
                        ? Jalali::date($record->period_from).' – '.($record->period_to ? Jalali::date($record->period_to) : '…')
                        : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label(__('admin.activity.who'))
                    ->placeholder('—'),
            ])))
            ->filters($showSeller ? [
                SelectFilter::make('method')
                    ->label(__('admin.finance.method'))
                    ->options((array) __('commission.methods')),
            ] : [])
            ->recordActions([
                Action::make('print')
                    ->label(__('admin.finance.print_settlement'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (SellerSettlement $record): string => route('admin.settlement-receipt', $record->uuid))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading(__('admin.finance.empty_settlements'));
    }
}
