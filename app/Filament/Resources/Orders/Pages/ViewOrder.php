<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CollectionPoint;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionException;
use App\Services\Orders\OrderWorkflow;
use App\Services\Payments\CashCollection;
use App\Services\Payments\CashCollectionService;
use App\Services\Payments\PaymentException;
use App\Support\AdminNotify;
use App\Support\Money;
use App\Support\Num;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * دیدن یک سفارش و پیش بردن آن: تأیید، آماده‌سازی، ارسال، تحویل، رد (لغو) و یادداشت مدیر.
 *
 * هر کار از `OrderWorkflow` ← `OrderStateMachine` می‌گذرد (اثر روی موجودی، تاریخچه و لاگ همان‌جا).
 * دکمه‌ها فقط وقتی دیده می‌شوند که آن گذار واقعاً ممکن باشد.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        /** @var Order $record */
        $record = $this->record;

        return (string) __('admin.orders.single').' '.$record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->collectCashAction(),
            $this->assignCourierAction(),
            $this->statusAction('confirm', Order::STATUS_CONFIRMED, Heroicon::OutlinedCheckCircle, 'success'),
            $this->statusAction('process', Order::STATUS_PROCESSING, Heroicon::OutlinedCog6Tooth, 'primary'),
            $this->statusAction('ship', Order::STATUS_SHIPPED, Heroicon::OutlinedTruck, 'primary'),
            $this->statusAction('deliver', Order::STATUS_DELIVERED, Heroicon::OutlinedHandRaised, 'success'),

            Action::make('reject')
                ->label(__('admin.orders.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Order $record): bool => $this->canAct() && $this->workflow()->can($record, Order::STATUS_CANCELLED))
                ->modalDescription(__('admin.orders.reject_help'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin.orders.reject_reason'))
                        ->placeholder(__('admin.orders.reject_reason_placeholder'))
                        ->required()
                        ->maxLength(255)
                        ->rows(3),
                ])
                ->action(function (Order $record, array $data): void {
                    $this->run(fn () => $this->workflow()->cancel($record, trim((string) $data['reason']), $this->actor()), 'rejected');
                }),

            Action::make('note')
                ->label(__('admin.orders.save_note'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->canAct())
                ->fillForm(fn (Order $record): array => ['admin_note' => $record->admin_note])
                ->schema([
                    Textarea::make('admin_note')
                        ->label(__('admin.orders.admin_note'))
                        ->placeholder(__('admin.orders.admin_note_placeholder'))
                        ->maxLength(1000)
                        ->rows(3),
                ])
                ->action(function (Order $record, array $data): void {
                    $this->workflow()->setAdminNote($record, $data['admin_note'] ?? null, $this->actor());
                    AdminNotify::done((string) __('admin.orders.note_saved'));
                    $this->refreshInfolist();
                }),
        ];
    }

    /**
     * «پول گرفته شد» (مرحله‌ی ۳): با **کد تحویل** مشتری. کد غلط ← هیچ‌چیز ثبت نمی‌شود.
     * پیک هم همین دکمه را می‌بیند، ولی فقط روی سفارش واگذارشده‌ی خودش و فقط دمِ در.
     */
    private function collectCashAction(): Action
    {
        return Action::make('collect_cash')
            ->label(__('admin.payments.collect'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record): bool => $this->cash()->canCollect($record, $this->actor()))
            ->modalHeading(__('admin.payments.collect'))
            ->modalDescription(fn (Order $record): string => (string) __('admin.payments.collect_help', [
                'amount' => Money::format(Money::sub($record->total, $record->paid_total)),
            ]))
            ->modalSubmitActionLabel(__('admin.payments.collect_submit'))
            ->fillForm(fn (Order $record): array => [
                'amount_received' => Num::toFa(Num::format(Money::sub($record->total, $record->paid_total))),
                'collection_point_id' => $record->collection_point_id,
                'mark_delivered' => true,
            ])
            // فورم ثابت است و خانه‌ی نمایندگی با visible پنهان می‌شود؛ خانه‌ی ساخته‌شده داخل
            // closureِ schema در مرورگر واقعی مقدارش را نمی‌فرستد (درس گرفته‌شده‌ی پروژه).
            ->schema([
                TextInput::make('delivery_code')
                    ->label(__('admin.payments.delivery_code'))
                    ->helperText(__('admin.payments.delivery_code_help'))
                    ->placeholder('۱۲۳۴۵')
                    ->required()
                    ->maxLength(10),
                TextInput::make('amount_received')
                    ->label(__('admin.payments.amount_received'))
                    ->helperText(__('admin.payments.amount_received_help'))
                    ->required()
                    ->maxLength(20),
                Select::make('collection_point_id')
                    ->label(__('admin.payments.collection_point'))
                    ->helperText(__('admin.payments.collection_point_help'))
                    ->options(fn (): array => CollectionPoint::query()->where('is_active', true)
                        ->orderBy('position')->get()
                        ->mapWithKeys(fn (CollectionPoint $point): array => [$point->id => $point->tr('name')])->all())
                    ->visible(fn (?Order $record): bool => $record?->isPickup() ?? false),
                TextInput::make('payer_name')
                    ->label(__('admin.payments.payer_name'))
                    ->helperText(__('admin.payments.payer_name_help'))
                    ->maxLength(100),
                Textarea::make('note')
                    ->label(__('admin.payments.note'))
                    ->helperText(__('admin.payments.note_help'))
                    ->maxLength(500)
                    ->rows(2),
                Toggle::make('mark_delivered')
                    ->label(__('admin.payments.mark_delivered'))
                    ->helperText(__('admin.payments.mark_delivered_help')),
            ])
            ->action(function (Order $record, array $data): void {
                $actor = $this->actor();
                if ($actor === null) {
                    return;
                }

                try {
                    $payment = $this->cash()->collect($record, new CashCollection(
                        deliveryCode: $data['delivery_code'] ?? null,
                        amountReceived: (string) ($data['amount_received'] ?? '0'),
                        collectionPointId: isset($data['collection_point_id']) && $data['collection_point_id'] !== null ? (int) $data['collection_point_id'] : null,
                        payerName: $data['payer_name'] ?? null,
                        note: $data['note'] ?? null,
                        markDelivered: (bool) ($data['mark_delivered'] ?? true),
                    ), $actor);
                } catch (PaymentException $e) {
                    AdminNotify::failed((string) __('admin.payments.not_collected'), $e->userMessage());

                    return;
                } catch (OrderTransitionException $e) {
                    // پول ثبت شد ولی وضعیت سفارش جلو نرفت (نباید پیش بیاید؛ تا معلوم باشد)
                    AdminNotify::failed((string) __('admin.orders.cannot'), $e->userMessage());

                    return;
                }

                AdminNotify::done(
                    (string) __('admin.payments.collected'),
                    (string) __('admin.payments.collected_body', ['receipt' => Num::toFa((string) $payment->receipt_number)]),
                );
                $this->refreshInfolist();
            });
    }

    /** واگذاری سفارش به یک پیک (بعد از این، آن پیک سفارش را در پنل خود می‌بیند). */
    private function assignCourierAction(): Action
    {
        return Action::make('assign_courier')
            ->label(__('admin.payments.assign_courier'))
            ->icon(Heroicon::OutlinedTruck)
            ->color('gray')
            ->visible(fn (Order $record): bool => $this->canAssign() && ! $record->isPickup() && $record->isOpen())
            ->modalDescription(__('admin.payments.assign_courier_help'))
            ->fillForm(fn (Order $record): array => ['courier_id' => $this->workflow()->courier($record)?->id])
            ->schema([
                Select::make('courier_id')
                    ->label(__('admin.payments.courier'))
                    ->helperText(__('admin.payments.courier_help'))
                    ->options(fn (): array => User::query()
                        ->where('role', UserRole::Courier->value)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder(__('admin.payments.courier_none')),
            ])
            ->action(function (Order $record, array $data): void {
                $courier = isset($data['courier_id']) && $data['courier_id'] !== null
                    ? User::query()->find($data['courier_id'])
                    : null;

                $this->workflow()->assignCourier($record, $courier, $this->actor());
                AdminNotify::done((string) __($courier === null ? 'admin.payments.courier_cleared' : 'admin.payments.courier_assigned', [
                    'name' => $courier?->name ?? '',
                ]));
                $this->refreshInfolist();
            });
    }

    /** یک دکمه‌ی تغییر وضعیت (تأیید، آماده‌سازی، ارسال، تحویل). */
    private function statusAction(string $name, string $to, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__('admin.orders.'.$name))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalDescription($this->help($name))
            ->visible(fn (Order $record): bool => $this->canAct() && $this->workflow()->can($record, $to))
            ->action(function (Order $record) use ($to, $name): void {
                $this->run(fn () => $this->workflow()->advance($record, $to, $this->actor()), $this->doneKey($name));
            });
    }

    /** توضیح کوتاه زیر عنوان پنجره‌ی تأیید (اگر برای این کار متن راهنما داشتیم). */
    private function help(string $name): ?string
    {
        $key = 'admin.orders.'.$name.'_help';
        $text = (string) __($key);

        return $text === $key ? null : $text;
    }

    /** کلید پیام موفقیت هر کار. */
    private function doneKey(string $name): string
    {
        return match ($name) {
            'confirm' => 'confirmed',
            'process' => 'processed',
            'ship' => 'shipped',
            'deliver' => 'delivered',
            default => $name,
        };
    }

    /** کار را انجام می‌دهد و خطای ماشین وضعیت را به پیام دری تبدیل می‌کند. */
    private function run(callable $callback, string $doneKey): void
    {
        try {
            $callback();
        } catch (OrderTransitionException $e) {
            AdminNotify::failed((string) __('admin.orders.cannot'), $e->userMessage());

            return;
        }

        AdminNotify::done((string) __('admin.orders.'.$doneKey));
        $this->refreshInfolist();
    }

    /** بعد از هر کار، سفارش و خانه‌های صفحه تازه می‌شود تا وضعیت و دکمه‌ها درست باشد. */
    private function refreshInfolist(): void
    {
        $this->record->refresh();
        $this->refreshFormData(['status', 'payment_status', 'cancel_reason', 'admin_note', 'total', 'paid_total']);
    }

    private function workflow(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function canAct(): bool
    {
        $user = $this->actor();

        return $user !== null && $user->role->canManageOrders();
    }

    private function canAssign(): bool
    {
        $user = $this->actor();

        return $user !== null && $user->role->canAssignCourier();
    }

    private function cash(): CashCollectionService
    {
        return app(CashCollectionService::class);
    }
}
