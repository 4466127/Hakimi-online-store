<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentEvents;

use App\Filament\Resources\PaymentEvents\Pages\ListPaymentEvents;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\PaymentEvent;
use App\Support\Jalali;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * پیام‌های سرور-به-سرور حساب‌پی و نتیجه‌ی هر کدام (مرحله‌ی ۵). فقط دیدن و «بررسی شد».
 * پیام‌های «نیاز به بررسی» (مثلاً پول برای سفارش لغوشده، مبلغ نادرست، دو بار پرداخت)
 * کار دستی مدیر می‌خواهد: پس دادن پول از داشبورد حساب‌پی یا برگرداندن سفارش.
 */
class PaymentEventResource extends Resource
{
    protected static ?string $model = PaymentEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.orders');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.payment_events.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.payment_events.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PaymentEvent::query()->needsReview()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('payment.order'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.payment_events.created_at'))
                    ->formatStateUsing(fn (PaymentEvent $record): string => Jalali::dateTime($record->created_at)),
                TextColumn::make('event_type')
                    ->label(__('admin.payment_events.type'))
                    ->formatStateUsing(fn (string $state): string => (string) __('admin.payment_events.types.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'payment_success' ? 'success' : 'gray'),
                TextColumn::make('outcome')
                    ->label(__('admin.payment_events.outcome'))
                    ->formatStateUsing(fn (string $state): string => (string) __('admin.payment_events.outcomes.'.$state))
                    ->badge()
                    ->color(fn (PaymentEvent $record): string => $record->needs_review ? 'danger' : ($record->outcome === PaymentEvent::PAID ? 'success' : 'gray'))
                    ->wrap(),
                TextColumn::make('order')
                    ->label(__('admin.payment_events.order'))
                    ->state(fn (PaymentEvent $record): ?string => $record->payment?->order?->number)
                    ->url(fn (PaymentEvent $record): ?string => $record->payment ? PaymentResource::getUrl('view', ['record' => $record->payment]) : null)
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label(__('admin.payment_events.amount'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Money::format($state))
                    ->placeholder('—'),
                TextColumn::make('transaction_id')
                    ->label(__('admin.payment_events.transaction'))
                    ->placeholder('—')
                    ->copyable()
                    ->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('needs_review')
                    ->label(__('admin.payment_events.needs_review'))
                    ->state(fn (PaymentEvent $record): bool => $record->needs_review && $record->reviewed_at === null)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check')
                    ->falseColor('gray'),
            ])
            ->filters([
                TernaryFilter::make('open_review')
                    ->label(__('admin.payment_events.filter_review'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('needs_review', true)->whereNull('reviewed_at'),
                        false: fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('needs_review', false)->orWhereNotNull('reviewed_at')),
                    ),
                SelectFilter::make('outcome')
                    ->label(__('admin.payment_events.outcome'))
                    ->options(collect(PaymentEvent::OUTCOMES)->mapWithKeys(fn (string $o) => [$o => __('admin.payment_events.outcomes.'.$o)])->all()),
            ])
            ->recordActions([
                Action::make('reviewed')
                    ->label(__('admin.payment_events.mark_reviewed'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.payment_events.mark_reviewed_help'))
                    ->visible(fn (PaymentEvent $record): bool => $record->needs_review && $record->reviewed_at === null
                        && (bool) auth()->user()?->can('update', $record))
                    ->action(function (PaymentEvent $record): void {
                        $record->forceFill(['reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
                        Notification::make()->success()->title(__('admin.payment_events.reviewed_done'))->send();
                    }),
            ])
            ->emptyStateHeading(__('admin.payment_events.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentEvents::route('/'),
        ];
    }
}
