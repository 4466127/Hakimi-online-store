<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\RelationManagers;

use App\Models\SellerTransaction;
use App\Support\Jalali;
use App\Support\Num;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * دفتر حساب فروشنده (فقط دیدن): طلب از فروش، برگشت، تسویه، اصلاح — با مانده بعد از هر ردیف.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.finance.transactions');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('creator'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.finance.occurred_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state)),
                TextColumn::make('type')
                    ->label(__('admin.finance.type'))
                    ->formatStateUsing(fn (SellerTransaction $record): string => $record->typeLabel())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'earning' => 'success',
                        'reversal' => 'danger',
                        'payout' => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('amount')
                    ->label(__('admin.finance.amount'))
                    ->formatStateUsing(fn ($state): string => Num::format((string) $state, 2))
                    ->color(fn ($state): string => str_starts_with((string) $state, '-') ? 'danger' : 'success'),
                TextColumn::make('balance_after')
                    ->label(__('admin.finance.balance_after'))
                    ->formatStateUsing(fn ($state): string => Num::format((string) $state, 2))
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label(__('admin.finance.description'))
                    ->placeholder('—')
                    ->limit(50),
                TextColumn::make('creator.name')
                    ->label(__('admin.activity.who'))
                    ->placeholder(__('admin.activity.system')),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('—');
    }
}
