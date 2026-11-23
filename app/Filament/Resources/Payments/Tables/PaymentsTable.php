<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\CashCollectionService;
use App\Services\Payments\PaymentException;
use App\Support\AdminNotify;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * لیست پرداخت‌ها: تازه‌ترین بالا. «پول دست پیک» با نشان زرد دیده می‌شود و با انتخاب ردیف‌ها
 * و دکمه‌ی «پول را از پیک گرفتم» تسویه می‌شود.
 */
class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('admin.payments.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['order', 'collector']))
            ->columns([
                TextColumn::make('receipt_number')
                    ->label(__('admin.payments.receipt_number'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Num::toFa($state))
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('order.number')
                    ->label(__('admin.payments.order'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Num::toFa($state))
                    ->searchable()
                    ->url(fn (Payment $record): ?string => $record->order === null
                        ? null
                        : route('filament.admin.resources.orders.view', ['record' => $record->order_id])),
                TextColumn::make('status')
                    ->label(__('admin.payments.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) __('checkout.payment_status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        Payment::PAID => 'success',
                        Payment::FAILED => 'danger',
                        Payment::AWAITING_COLLECTION, Payment::PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount_received')
                    ->label(__('admin.payments.amount'))
                    ->formatStateUsing(fn ($state, Payment $record): string => Money::format($state ?? $record->amount))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('collected_at_type')
                    ->label(__('admin.payments.place'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (string) __('payments.place.'.$state))
                    ->toggleable(),
                TextColumn::make('collector.name')
                    ->label(__('admin.payments.collector'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('collected_at')
                    ->label(__('admin.payments.collected_at'))
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : Jalali::dateTime($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('courier_cash_status')
                    ->label(__('admin.payments.courier_cash'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (string) __('payments.courier_cash.'.$state))
                    ->color(fn (?string $state): string => $state === Payment::HELD_BY_COURIER ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.payments.filter_status'))
                    ->options([
                        Payment::AWAITING_COLLECTION => (string) __('checkout.payment_status.awaiting_collection'),
                        Payment::PENDING => (string) __('checkout.payment_status.pending'),
                        Payment::PAID => (string) __('checkout.payment_status.paid'),
                        Payment::FAILED => (string) __('checkout.payment_status.failed'),
                    ]),
                SelectFilter::make('collected_at_type')
                    ->label(__('admin.payments.filter_place'))
                    ->options([
                        Payment::AT_DOOR => (string) __('payments.place.door'),
                        Payment::AT_POINT => (string) __('payments.place.point'),
                    ]),
                Filter::make('held_by_courier')
                    ->label(__('admin.payments.filter_held'))
                    ->query(fn (Builder $query): Builder => $query->heldByCourier()),
                Filter::make('today')
                    ->label(__('admin.payments.filter_today'))
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->startOfDay())),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('receipt')
                    ->label(__('admin.payments.print_receipt'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (Payment $record): bool => $record->isPaid())
                    ->url(fn (Payment $record): string => route('admin.receipt', ['payment' => $record->uuid]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('handover')
                        ->label(__('admin.payments.handover'))
                        ->icon('heroicon-o-inbox-arrow-down')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('admin.payments.handover_help'))
                        ->visible(fn (): bool => self::canReceive())
                        ->action(function (Collection $records): void {
                            $user = auth()->user();
                            if (! $user instanceof User) {
                                return;
                            }

                            try {
                                $done = app(CashCollectionService::class)->handOver($records, $user);
                            } catch (PaymentException $e) {
                                AdminNotify::failed((string) __('admin.payments.handover_failed'), $e->userMessage());

                                return;
                            }

                            AdminNotify::done((string) __('admin.payments.handover_done', ['count' => Num::toFa((string) $done)]));
                        }),
                ]),
            ])
            ->emptyStateHeading(__('admin.payments.not_paid_yet'));
    }

    private static function canReceive(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canReceiveCourierCash();
    }
}
