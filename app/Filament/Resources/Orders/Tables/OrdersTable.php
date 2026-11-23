<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use App\Support\Phone;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * لیست سفارش‌ها: تازه‌ترین بالا، سفارش‌های منتظر تأیید اول.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('admin.orders.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('items'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.number'))
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('placed_at')
                    ->label(__('admin.orders.placed_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->sortable(),
                TextColumn::make('purchaser_name')
                    ->label(__('admin.orders.purchaser'))
                    ->description(fn (Order $record): string => Phone::display($record->purchaser_phone))
                    ->searchable(['purchaser_name', 'purchaser_phone', 'recipient_name', 'recipient_phone']),
                TextColumn::make('status')
                    ->label(__('admin.orders.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('checkout.status.'.$state))
                    ->color(fn (string $state): string => Order::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('payment_status')
                    ->label(__('admin.orders.payment_status'))
                    ->formatStateUsing(fn (string $state): string => (string) __('checkout.payment_status.'.$state))
                    ->toggleable(),
                TextColumn::make('delivery_method')
                    ->label(__('admin.orders.delivery_method'))
                    ->formatStateUsing(fn (string $state): string => (string) __('checkout.delivery.'.($state === 'pickup' ? 'pickup' : 'home')))
                    ->toggleable(),
                TextColumn::make('delivery_date')
                    ->label(__('admin.orders.delivery_day'))
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : Jalali::date($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label(__('admin.orders.items_count'))
                    ->formatStateUsing(fn ($state): string => Num::toFa((string) $state))
                    ->toggleable(),
                TextColumn::make('total')
                    ->label(__('admin.orders.total'))
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->weight('bold')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.orders.filter_status'))
                    ->options(fn (): array => collect(Order::STATUSES)
                        ->mapWithKeys(fn (string $s): array => [$s => (string) __('checkout.status.'.$s)])->all()),
                SelectFilter::make('payment_status')
                    ->label(__('admin.orders.filter_payment'))
                    ->options(fn (): array => collect(Order::PAYMENT_STATUSES)
                        ->mapWithKeys(fn (string $s): array => [$s => (string) __('checkout.payment_status.'.$s)])->all()),
                SelectFilter::make('delivery_method')
                    ->label(__('admin.orders.filter_method'))
                    ->options([
                        'delivery' => (string) __('checkout.delivery.home'),
                        'pickup' => (string) __('checkout.delivery.pickup'),
                    ]),
                Filter::make('today')
                    ->label(__('admin.orders.filter_today'))
                    ->query(fn (Builder $query): Builder => $query->where('placed_at', '>=', now()->startOfDay())),
                Filter::make('needs_stock_check')
                    ->label(__('admin.orders.filter_needs_stock'))
                    ->query(fn (Builder $query): Builder => $query->where('needs_stock_check', true)),
            ])
            // اول سفارش‌های منتظر تأیید، بعد تازه‌ترین
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('placed_at')
                ->orderByDesc('id'))
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('admin.orders.empty'));
    }
}
