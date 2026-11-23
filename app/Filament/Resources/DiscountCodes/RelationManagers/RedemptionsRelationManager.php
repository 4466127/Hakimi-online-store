<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\DiscountCodeRedemption;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Phone;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * سفارش‌هایی که این کد تخفیف را استفاده کردند (فقط دیدن؛ تاریخچه ویرایش نمی‌شود).
 */
class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.discounts.redemptions');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order'))
            ->columns([
                TextColumn::make('order.number')
                    ->label(__('admin.orders.number'))
                    ->url(fn (DiscountCodeRedemption $record): ?string => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null)
                    ->weight('bold'),
                TextColumn::make('phone')
                    ->label(__('admin.orders.purchaser_phone'))
                    ->formatStateUsing(fn ($state): string => Phone::display($state)),
                TextColumn::make('amount')
                    ->label(__('admin.discounts.redeemed_amount'))
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextColumn::make('returned_amount')
                    ->label(__('admin.discounts.returned_amount'))
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextColumn::make('status')
                    ->label(__('admin.discounts.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('admin.discounts.redemption_statuses.'.$state))
                    ->color(fn (string $state): string => $state === DiscountCodeRedemption::ACTIVE ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label(__('admin.orders.placed_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state)),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('admin.discounts.no_redemptions'));
    }
}
