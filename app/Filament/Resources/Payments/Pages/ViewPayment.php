<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\CashCollectionService;
use App\Services\Payments\Gateways\HesabPayGateway;
use App\Services\Payments\PaymentException;
use App\Support\AdminNotify;
use App\Support\Money;
use App\Support\Num;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** دیدن یک پرداخت: رسید چاپی و تحویل گرفتن پول از پیک. */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        /** @var Payment $record */
        $record = $this->record;

        return (string) __('admin.payments.single').' '.Num::toFa((string) ($record->receipt_number ?? $record->order?->number ?? ''));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receipt')
                ->label(__('admin.payments.print_receipt'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (Payment $record): bool => $record->isPaid())
                ->url(fn (Payment $record): string => route('admin.receipt', ['payment' => $record->uuid]))
                ->openUrlInNewTab(),

            // تطبیق با حساب‌پی: فقط نشان می‌دهد، هیچ وضعیتی را عوض نمی‌کند (پرداخت‌شده فقط با پیام امضاشده)
            Action::make('gateway_lookup')
                ->label(__('admin.payments.gateway_lookup'))
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('gray')
                ->visible(fn (Payment $record): bool => $record->provider === HesabPayGateway::CODE && $record->provider_reference !== null
                    && $this->canReceive() && app(HesabPayGateway::class)->isReady())
                ->action(function (Payment $record): void {
                    try {
                        $result = app(HesabPayGateway::class)->lookup((string) $record->provider_reference);
                    } catch (PaymentException $e) {
                        AdminNotify::failed((string) __('admin.payments.gateway_lookup_failed'), $e->userMessage());

                        return;
                    }
                    if (! $result['found']) {
                        AdminNotify::failed((string) __('admin.payments.gateway_lookup_failed'), (string) __('admin.payments.gateway_lookup_missing', ['http' => $result['http']]));

                        return;
                    }
                    $data = $result['data'];
                    AdminNotify::done((string) __('admin.payments.gateway_lookup_result', [
                        'amount' => is_numeric($data['amount'] ?? null) ? Money::format((string) $data['amount']) : '—',
                        'status' => is_scalar($data['status'] ?? null) ? (string) $data['status'] : '—',
                        'date' => is_scalar($data['transaction_date'] ?? null) ? (string) $data['transaction_date'] : '—',
                        'to' => is_scalar($data['to_account_number'] ?? null) ? (string) $data['to_account_number'] : '—',
                    ]));
                }),

            Action::make('handover')
                ->label(__('admin.payments.handover'))
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('admin.payments.handover_help'))
                ->visible(fn (Payment $record): bool => $record->courier_cash_status === Payment::HELD_BY_COURIER && $this->canReceive())
                ->action(function (Payment $record): void {
                    $user = auth()->user();
                    if (! $user instanceof User) {
                        return;
                    }

                    try {
                        app(CashCollectionService::class)->handOver([$record], $user);
                    } catch (PaymentException $e) {
                        AdminNotify::failed((string) __('admin.payments.handover_failed'), $e->userMessage());

                        return;
                    }

                    AdminNotify::done((string) __('admin.payments.handover_done', ['count' => Num::toFa('1')]));
                    $this->record->refresh();
                    $this->refreshFormData(['courier_cash_status', 'handed_over_at']);
                }),
        ];
    }

    private function canReceive(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canReceiveCourierCash();
    }
}
