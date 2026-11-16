<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\Pages;

use App\Enums\SellerStatus;
use App\Filament\Resources\Sellers\SellerResource;
use App\Models\Seller;
use App\Models\User;
use App\Services\SellerOnboarding;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

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
