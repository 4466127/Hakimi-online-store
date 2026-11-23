<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitionException;
use App\Services\Orders\OrderWorkflow;
use App\Support\AdminNotify;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
}
