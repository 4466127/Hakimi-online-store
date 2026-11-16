<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\Pages;

use App\Enums\SellerStatus;
use App\Filament\Resources\Sellers\SellerResource;
use App\Models\Seller;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\SellerOnboarding;
use App\Support\Money;
use App\Support\Num;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * دیدن درخواست فروشنده و تأیید / رد / بستن آن.
 */
class ViewSeller extends ViewRecord
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(fn (Seller $record): string => $record->status === SellerStatus::Suspended ? __('admin.sellers.reactivate') : __('admin.sellers.approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin.sellers.approve_confirm'))
                ->visible(fn (Seller $record): bool => in_array($record->status, [SellerStatus::Pending, SellerStatus::Rejected, SellerStatus::Suspended], true)
                    && $this->canReview($record))
                ->action(function (Seller $record): void {
                    app(SellerOnboarding::class)->approve($record, $this->reviewer());
                    Notification::make()->success()->title(__('admin.sellers.approved'))->send();
                    $this->refreshFormData(['status', 'reviewed_at', 'review_note']);
                }),

            Action::make('reject')
                ->label(__('admin.sellers.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (Seller $record): bool => $record->status === SellerStatus::Pending && $this->canReview($record))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin.sellers.reject_reason'))
                        ->helperText(__('admin.sellers.reject_reason_help'))
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (Seller $record, array $data): void {
                    app(SellerOnboarding::class)->reject($record, $this->reviewer(), trim((string) $data['reason']));
                    Notification::make()->success()->title(__('admin.sellers.rejected'))->send();
                    $this->refreshFormData(['status', 'reviewed_at', 'review_note']);
                }),

            Action::make('suspend')
                ->label(__('admin.sellers.suspend'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (Seller $record): bool => $record->status === SellerStatus::Approved && $this->canReview($record))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin.sellers.suspend_reason'))
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (Seller $record, array $data): void {
                    app(SellerOnboarding::class)->suspend($record, $this->reviewer(), trim((string) $data['reason']));
                    Notification::make()->success()->title(__('admin.sellers.suspended'))->send();
                    $this->refreshFormData(['status', 'reviewed_at', 'review_note']);
                }),

            Action::make('settle')
                ->label(__('admin.finance.settle'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('primary')
                ->visible(fn (Seller $record): bool => $this->canReview($record)
                    && Money::compare(app(CommissionService::class)->balance($record), 0) > 0)
                ->modalDescription(fn (Seller $record): string => __('admin.finance.settle_help', ['balance' => Num::format(app(CommissionService::class)->balance($record), 2)]))
                ->schema([
                    TextInput::make('amount')
                        ->label(__('admin.finance.amount'))
                        ->numeric()->minValue(0.01)->step('0.01')
                        ->default(fn (Seller $record): string => app(CommissionService::class)->balance($record))
                        ->required(),
                    Select::make('method')
                        ->label(__('admin.finance.method'))
                        ->options((array) __('commission.methods'))
                        ->default('cash')
                        ->required(),
                    TextInput::make('reference')->label(__('admin.finance.reference'))->maxLength(100),
                    DatePicker::make('paid_at')->label(__('admin.finance.paid_at'))->default(now())->required(),
                    DatePicker::make('period_from')->label(__('admin.finance.period_from')),
                    DatePicker::make('period_to')->label(__('admin.finance.period_to'))->afterOrEqual('period_from'),
                    Textarea::make('notes')->label(__('admin.finance.notes'))->rows(2)->maxLength(1000),
                ])
                ->action(function (Seller $record, array $data, Action $action): void {
                    try {
                        app(CommissionService::class)->settle(
                            $record,
                            (string) $data['amount'],
                            (string) $data['method'],
                            $this->reviewer(),
                            $data['reference'] ?? null,
                            $data['notes'] ?? null,
                            Carbon::parse($data['paid_at']),
                            filled($data['period_from'] ?? null) ? Carbon::parse($data['period_from']) : null,
                            filled($data['period_to'] ?? null) ? Carbon::parse($data['period_to']) : null,
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title(__('admin.finance.over_balance', ['balance' => Num::format(app(CommissionService::class)->balance($record), 2)]))->send();
                        $action->halt();
                    }
                    Notification::make()->success()->title(__('admin.finance.settled'))->send();
                }),

            Action::make('adjust')
                ->label(__('admin.finance.adjust'))
                ->icon(Heroicon::OutlinedScale)
                ->color('gray')
                ->visible(fn (Seller $record): bool => $this->canReview($record) && $record->status !== SellerStatus::Draft)
                ->modalDescription(__('admin.finance.adjust_help'))
                ->schema([
                    TextInput::make('amount')
                        ->label(__('admin.finance.amount'))
                        ->numeric()->step('0.01')
                        ->required(),
                    Textarea::make('reason')->label(__('admin.finance.reason'))->required()->maxLength(255)->rows(2),
                ])
                ->action(function (Seller $record, array $data, Action $action): void {
                    try {
                        app(CommissionService::class)->adjust($record, (string) $data['amount'], (string) $data['reason'], $this->reviewer());
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title(__('admin.finance.invalid'))->send();
                        $action->halt();
                    }
                    Notification::make()->success()->title(__('admin.finance.adjusted'))->send();
                }),

            Action::make('notes')
                ->label(__('admin.sellers.admin_notes'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (Seller $record): bool => $this->canReview($record))
                ->fillForm(fn (Seller $record): array => ['admin_notes' => $record->admin_notes])
                ->schema([
                    Textarea::make('admin_notes')
                        ->label(__('admin.sellers.admin_notes'))
                        ->helperText(__('admin.sellers.admin_notes_help'))
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(function (Seller $record, array $data): void {
                    $record->update(['admin_notes' => $data['admin_notes'] ?? null]);
                    Notification::make()->success()->title(__('admin.saved'))->send();
                    $this->refreshFormData(['admin_notes']);
                }),
        ];
    }

    private function canReview(Seller $record): bool
    {
        return (bool) auth()->user()?->can('review', $record);
    }

    private function reviewer(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
