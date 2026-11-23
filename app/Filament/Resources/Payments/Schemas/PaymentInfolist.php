<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** صفحه‌ی یک پرداخت: مبلغ، رسید، گیرنده‌ی پول، محل، و تسویه‌ی پول پیک. */
class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.payments.section_payment'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('admin.payments.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => (string) __('checkout.payment_status.'.$state))
                            ->color(fn (string $state): string => match ($state) {
                                Payment::PAID => 'success',
                                Payment::FAILED => 'danger',
                                default => 'warning',
                            }),
                        TextEntry::make('order.number')
                            ->label(__('admin.payments.order'))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Num::toFa($state))
                            ->url(fn (Payment $record): ?string => $record->order === null
                                ? null
                                : route('filament.admin.resources.orders.view', ['record' => $record->order_id])),
                        TextEntry::make('method_code')
                            ->label(__('admin.payments.method'))
                            ->formatStateUsing(fn (string $state): string => PaymentMethod::query()->where('code', $state)->first()?->tr('name') ?? $state),
                        TextEntry::make('amount')
                            ->label(__('admin.payments.amount'))
                            ->formatStateUsing(fn ($state): string => Money::format($state)),
                        TextEntry::make('amount_received')
                            ->label(__('payments.receipt.amount'))
                            ->formatStateUsing(fn ($state): string => $state === null ? '—' : Money::format($state))
                            ->weight('bold'),
                        TextEntry::make('failed_reason')
                            ->label(__('checkout.order.cancel_reason'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.payments.section_cash'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('receipt_number')
                            ->label(__('admin.payments.receipt_number'))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Num::toFa($state))
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('collected_at_type')
                            ->label(__('admin.payments.place'))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (string) __('payments.place.'.$state)),
                        TextEntry::make('collectionPoint.name')
                            ->label(__('admin.payments.collection_point'))
                            ->formatStateUsing(fn (Payment $record): string => $record->collectionPoint?->tr('name') ?? '—'),
                        TextEntry::make('collector.name')
                            ->label(__('admin.payments.collector'))
                            ->placeholder('—'),
                        TextEntry::make('collected_at')
                            ->label(__('admin.payments.collected_at'))
                            ->formatStateUsing(fn ($state): string => $state === null ? '—' : Jalali::dateTime($state)),
                        TextEntry::make('delivery_code_verified')
                            ->label(__('admin.payments.code_verified'))
                            ->badge()
                            ->color(fn ($state): string => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn ($state): string => (string) __($state ? 'admin.yes' : 'admin.no')),
                        TextEntry::make('courier_cash_status')
                            ->label(__('admin.payments.courier_cash'))
                            ->badge()
                            ->color(fn (?string $state): string => $state === Payment::HELD_BY_COURIER ? 'warning' : 'gray')
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : (string) __('payments.courier_cash.'.$state)),
                        TextEntry::make('receiver.name')
                            ->label(__('admin.payments.receiver'))
                            ->placeholder('—'),
                        TextEntry::make('handed_over_at')
                            ->label(__('admin.payments.handed_over_at'))
                            ->formatStateUsing(fn ($state): string => $state === null ? '—' : Jalali::dateTime($state)),
                        TextEntry::make('payer_name')
                            ->label(__('admin.payments.payer'))
                            ->placeholder('—'),
                        TextEntry::make('note')
                            ->label(__('admin.payments.note'))
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
