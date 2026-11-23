<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SellerOrder;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use App\Support\Phone;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * صفحه‌ی دیدن یک سفارش در پنل: وضعیت، خریدار و گیرنده، تحویل و پرداخت، اجناس، مبلغ‌ها و تاریخچه.
 * کارهای مدیر (تأیید، رد، …) در `Pages\ViewOrder` است.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.orders.section_review'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('admin.orders.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => (string) __('checkout.status.'.$state))
                            ->color(fn (string $state): string => Order::STATUS_COLORS[$state] ?? 'gray'),
                        TextEntry::make('payment_status')
                            ->label(__('admin.orders.payment_status'))
                            ->formatStateUsing(fn (string $state): string => (string) __('checkout.payment_status.'.$state)),
                        TextEntry::make('placed_at')
                            ->label(__('admin.orders.placed_at'))
                            ->formatStateUsing(fn ($state): string => Jalali::dateTime($state)),
                        TextEntry::make('delivery_code')
                            ->label(__('admin.orders.delivery_code'))
                            ->helperText(__('admin.orders.delivery_code_help'))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Num::toFa($state))
                            ->weight('bold')
                            ->size('lg')
                            ->copyable(),
                        TextEntry::make('needs_stock_check')
                            ->label(__('admin.orders.needs_stock_check'))
                            ->visible(fn (Order $record): bool => (bool) $record->needs_stock_check)
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (): string => (string) __('admin.orders.needs_stock_check'))
                            ->columnSpan(2),
                        TextEntry::make('cancel_reason')
                            ->label(__('checkout.order.cancel_reason'))
                            ->visible(fn (Order $record): bool => $record->cancel_reason !== null)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.orders.section_customer'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('purchaser_name')->label(__('admin.orders.purchaser')),
                        TextEntry::make('purchaser_phone')
                            ->label(__('admin.orders.purchaser_phone'))
                            ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                            ->copyable(),
                        TextEntry::make('customer.name')
                            ->label(__('admin.orders.customer'))
                            ->placeholder(__('admin.orders.guest')),
                        TextEntry::make('recipient_name')->label(__('admin.orders.recipient')),
                        TextEntry::make('recipient_phone')
                            ->label(__('admin.orders.recipient_phone'))
                            ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                            ->copyable(),
                        TextEntry::make('is_gift')
                            ->label(__('admin.orders.is_gift'))
                            ->formatStateUsing(fn ($state): string => (string) __($state ? 'admin.yes' : 'admin.no')),
                    ]),

                Section::make(__('admin.orders.section_delivery'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('delivery_method')
                            ->label(__('admin.orders.delivery_method'))
                            ->formatStateUsing(fn (string $state): string => (string) __('checkout.delivery.'.($state === 'pickup' ? 'pickup' : 'home'))),
                        TextEntry::make('delivery_date')
                            ->label(__('admin.orders.delivery_day'))
                            ->formatStateUsing(fn ($state): string => $state === null ? '—' : Jalali::weekday($state->format('Y-m-d')).'، '.Jalali::formatLong($state->format('Y-m-d'))),
                        TextEntry::make('payment_method_code')
                            ->label(__('admin.orders.payment_method'))
                            ->formatStateUsing(fn (string $state): string => PaymentMethod::query()->where('code', $state)->first()?->tr('name') ?? $state),
                        TextEntry::make('address')
                            ->label(__('admin.orders.address'))
                            ->state(fn (Order $record): string => $record->isPickup()
                                ? ($record->collectionPoint?->tr('name') ?? '—')
                                : collect([$record->province?->tr('name'), $record->city, $record->district, $record->address_line])->filter()->implode('، '))
                            ->columnSpan(2)
                            ->copyable(),
                        TextEntry::make('delivery_instructions')
                            ->label(__('admin.orders.instructions'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.orders.items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label(__('admin.orders.item'))
                                    ->formatStateUsing(fn (string $state, OrderItem $record): string => $state.($record->variant_label === null ? '' : ' — '.$record->variant_label))
                                    ->columnSpan(2),
                                TextEntry::make('qty')
                                    ->label(__('admin.orders.qty'))
                                    ->formatStateUsing(fn ($state): string => Num::toFa((string) $state)),
                                TextEntry::make('line_total')
                                    ->label(__('admin.orders.line_total'))
                                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                                TextEntry::make('sku')->label('SKU')->placeholder('—'),
                                TextEntry::make('unit_price')
                                    ->label(__('admin.orders.unit_price'))
                                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                                TextEntry::make('seller')
                                    ->label(__('admin.orders.seller'))
                                    ->state(fn (OrderItem $record): string => $record->seller_id === null
                                        ? (string) __('admin.orders.own_products')
                                        : ($record->seller?->store_name ?: $record->seller?->full_name ?? '—'))
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make(__('admin.orders.section_money'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label(__('admin.orders.subtotal'))
                            ->formatStateUsing(fn ($state): string => Money::format($state)),
                        TextEntry::make('discount_total')
                            ->label(__('admin.orders.discount_total'))
                            ->formatStateUsing(fn ($state, $record): string => Money::format($state)
                                .($record->discount_code ? ' ('.$record->discount_code.')' : ''))
                            ->visible(fn ($record): bool => Money::compare($record->discount_total, 0) > 0),
                        TextEntry::make('delivery_fee')
                            ->label(__('admin.orders.delivery_fee'))
                            ->formatStateUsing(fn ($state): string => Money::format($state)),
                        TextEntry::make('total')
                            ->label(__('admin.orders.total'))
                            ->formatStateUsing(fn ($state): string => Money::format($state))
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('paid_total')
                            ->label(__('admin.orders.paid_total'))
                            ->formatStateUsing(fn ($state): string => Money::format($state)),
                        TextEntry::make('sellers')
                            ->label(__('admin.orders.sellers_count'))
                            ->state(fn (Order $record): string => $record->sellerOrders
                                ->map(fn (SellerOrder $so): string => ($so->seller?->store_name ?: $so->seller?->full_name ?? (string) __('admin.orders.own_products'))
                                    .' — '.Money::format($so->gross_subtotal).' ('.__('checkout.status.'.$so->status).')')
                                ->implode(' • '))
                            ->columnSpanFull(),
                    ]),

                // پول نقد و رسید (مرحله‌ی ۳): چه کسی، کجا، چقدر، با کدام رسید
                Section::make(__('admin.payments.section_cash'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payments_receipt')
                            ->label(__('admin.payments.receipt_number'))
                            ->state(fn (Order $record): string => self::paid($record)?->receipt_number === null
                                ? '—'
                                : Num::toFa((string) self::paid($record)?->receipt_number))
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('payments_collector')
                            ->label(__('admin.payments.collector'))
                            ->state(fn (Order $record): string => self::paid($record)?->collector?->name ?? '—'),
                        TextEntry::make('payments_place')
                            ->label(__('admin.payments.place'))
                            ->state(function (Order $record): string {
                                $place = self::paid($record)?->collected_at_type;

                                return $place === null ? '—' : (string) __('payments.place.'.$place);
                            }),
                        TextEntry::make('payments_courier_cash')
                            ->label(__('admin.payments.courier_cash'))
                            ->state(function (Order $record): string {
                                $status = self::paid($record)?->courier_cash_status;

                                return $status === null ? '—' : (string) __('payments.courier_cash.'.$status);
                            }),
                        TextEntry::make('assigned_courier')
                            ->label(__('admin.payments.assigned_courier'))
                            ->state(fn (Order $record): string => $record->sellerOrders
                                ->map(fn (SellerOrder $so) => $so->courier?->name)
                                ->filter()->unique()->implode('، ') ?: '—'),
                        TextEntry::make('payments_receipt_link')
                            ->label(__('admin.payments.print_receipt'))
                            ->state(fn (Order $record): string => self::paid($record) === null ? '—' : (string) __('admin.payments.print_receipt'))
                            ->url(fn (Order $record): ?string => self::paid($record) === null
                                ? null
                                : route('admin.receipt', ['payment' => self::paid($record)?->uuid]))
                            ->openUrlInNewTab(),
                    ]),

                Section::make(__('admin.orders.section_note'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_note')
                            ->label(__('admin.orders.customer_note'))
                            ->placeholder('—'),
                        TextEntry::make('admin_note')
                            ->label(__('admin.orders.admin_note'))
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.orders.history'))
                    ->collapsible()
                    ->schema([
                        TextEntry::make('history')
                            // برچسب فقط برای صفحه‌خوان (عنوان بخش بالایش هست)
                            ->hiddenLabel()
                            ->state(fn (Order $record): array => self::history($record))
                            ->listWithLineBreaks()
                            ->placeholder(__('admin.orders.history_empty')),
                    ]),
            ]);
    }

    /** آخرین پرداختِ «پول رسید» این سفارش (اگر هنوز پولی گرفته نشده: null). */
    private static function paid(Order $order): ?Payment
    {
        return $order->payments->where('status', Payment::PAID)->last();
    }

    /**
     * تاریخچه‌ی وضعیت خودِ سفارش، هر تغییر یک خط (تغییرهای هر فروشنده در لاگ فعالیت است).
     *
     * @return list<string>
     */
    private static function history(Order $order): array
    {
        $rows = OrderStatusHistory::query()
            ->where('subject_type', 'order')
            ->where('subject_id', $order->id)
            ->with('user')
            ->orderBy('id')
            ->get();

        return $rows->map(function (OrderStatusHistory $row): string {
            $what = $row->from_status === null
                ? (string) __('admin.orders.history_first', ['to' => __('checkout.status.'.$row->to_status)])
                : (string) __('admin.orders.history_change', [
                    'from' => __('checkout.status.'.$row->from_status),
                    'to' => __('checkout.status.'.$row->to_status),
                ]);
            $who = (string) __('admin.orders.history_by', ['name' => $row->user?->name ?? __('admin.orders.history_system')]);

            return Jalali::dateTime($row->created_at).' — '.$what.' — '.$who.($row->note === null ? '' : ' («'.$row->note.'»)');
        })->values()->all();
    }
}
