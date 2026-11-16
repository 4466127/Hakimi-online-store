<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\Tables;

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Support\Jalali;
use App\Support\Phone;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('admin.sellers.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'province']))
            ->columns([
                TextColumn::make('store_name')
                    ->label(__('admin.sellers.store_name'))
                    ->placeholder('—')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label(__('admin.sellers.full_name'))
                    ->searchable(),
                TextColumn::make('customer.phone')
                    ->label(__('admin.sellers.phone'))
                    ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.sellers.status'))
                    ->formatStateUsing(fn (SellerStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (SellerStatus $state): string => $state->color()),
                TextColumn::make('type')
                    ->label(__('admin.sellers.type'))
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('province')
                    ->label(__('admin.sellers.province'))
                    ->state(fn (Seller $record): ?string => $record->province?->tr('name'))
                    ->placeholder('—'),
                TextColumn::make('submitted_at')
                    ->label(__('admin.sellers.submitted_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.sellers.status'))
                    ->options(SellerStatus::options()),
            ])
            // اول درخواست‌های منتظر، بعد بقیه (تازه‌ترین بالا)
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 WHEN 'suspended' THEN 3 ELSE 4 END")
                ->orderByDesc('submitted_at')
                ->orderByDesc('id'))
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.sellers.empty'));
    }
}
