<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\Schemas;

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Services\Commission\CommissionService;
use App\Support\Jalali;
use App\Support\Num;
use App\Support\Phone;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * صفحه‌ی دیدن درخواست فروشنده در پنل.
 */
class SellerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.sellers.section_review'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('admin.sellers.status'))
                            ->formatStateUsing(fn (SellerStatus $state): string => $state->label())
                            ->badge()
                            ->color(fn (SellerStatus $state): string => $state->color()),
                        TextEntry::make('submitted_at')
                            ->label(__('admin.sellers.submitted_at'))
                            ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                            ->placeholder('—'),
                        TextEntry::make('reviewer.name')
                            ->label(__('admin.sellers.reviewed_by'))
                            ->placeholder('—'),
                        TextEntry::make('reviewed_at')
                            ->label(__('admin.sellers.reviewed_at'))
                            ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                            ->placeholder('—'),
                        TextEntry::make('review_note')
                            ->label(__('admin.sellers.review_note'))
                            ->placeholder('—')
                            ->columnSpan(2),
                        TextEntry::make('admin_notes')
                            ->label(__('admin.sellers.admin_notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.finance.section'))
                    ->columns(3)
                    ->visible(fn (Seller $record): bool => $record->approved_at !== null)
                    ->schema([
                        TextEntry::make('balance')
                            ->label(__('admin.finance.balance'))
                            ->state(fn (Seller $record): string => Num::format(app(CommissionService::class)->balance($record), 2).' '.__('admin.finance.afn'))
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('total_sales')
                            ->label(__('admin.finance.total_sales'))
                            ->state(fn (Seller $record): string => Num::format((string) $record->commissionEntries()->sum('gross_amount'), 2)),
                        TextEntry::make('total_commission')
                            ->label(__('admin.finance.total_commission'))
                            ->state(fn (Seller $record): string => Num::format((string) $record->commissionEntries()->sum('commission_amount'), 2)),
                        TextEntry::make('settlement_cycle')
                            ->label(__('admin.finance.settlement_cycle'))
                            ->formatStateUsing(fn (?string $state): string => (string) __('commission.cycles.'.($state ?? 'monthly'))),
                        TextEntry::make('free_pickup_until')
                            ->label(__('admin.finance.free_pickup_until'))
                            ->formatStateUsing(fn ($state): string => Jalali::date($state))
                            ->placeholder('—'),
                        TextEntry::make('approved_at')
                            ->label(__('admin.finance.approved_at'))
                            ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                            ->placeholder('—'),
                    ]),

                Section::make(__('admin.sellers.section_identity'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('store_name')->label(__('admin.sellers.store_name'))->weight('bold')->placeholder('—'),
                        TextEntry::make('type')->label(__('admin.sellers.type'))
                            ->formatStateUsing(fn ($state): string => $state?->label() ?? '')->placeholder('—'),
                        TextEntry::make('customer.phone')->label(__('admin.sellers.phone'))
                            ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                            ->url(fn (Seller $record): ?string => Phone::telLink($record->customer?->phone)),
                        TextEntry::make('full_name')->label(__('admin.sellers.full_name'))->placeholder('—'),
                        TextEntry::make('father_name')->label(__('admin.sellers.father_name'))->placeholder('—'),
                        TextEntry::make('tazkira_number')->label(__('admin.sellers.tazkira_number'))->placeholder('—')->copyable(),
                        TextEntry::make('business_name')->label(__('admin.sellers.business_name'))->placeholder('—'),
                        TextEntry::make('license_number')->label(__('admin.sellers.license_number'))->placeholder('—'),
                        TextEntry::make('categories')->label(__('admin.sellers.categories'))
                            ->state(fn (Seller $record): string => $record->categories()->map(fn ($c) => $c->tr('name'))->implode('، '))
                            ->placeholder('—'),
                        TextEntry::make('flags')->label(__('admin.sellers.flags'))
                            ->state(fn (Seller $record): array => array_values(array_filter([
                                $record->is_local_producer ? __('seller.fields.is_local_producer') : null,
                                $record->is_social_seller ? __('seller.fields.is_social_seller') : null,
                                $record->has_brand ? __('seller.fields.has_brand') : null,
                            ])))
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('social_url')->label(__('admin.sellers.social_url'))->placeholder('—')
                            ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),
                        TextEntry::make('brand_name')->label(__('admin.sellers.brand_name'))->placeholder('—'),
                    ]),

                Section::make(__('admin.sellers.section_address'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('province')->label(__('admin.sellers.province'))
                            ->state(fn (Seller $record): ?string => $record->province?->tr('name'))->placeholder('—'),
                        TextEntry::make('city')->label(__('admin.sellers.city'))->placeholder('—'),
                        TextEntry::make('phone')->label(__('admin.sellers.shop_phone'))
                            ->formatStateUsing(fn (?string $state): string => Phone::display($state))->placeholder('—'),
                        TextEntry::make('address')->label(__('admin.sellers.address'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('terms_accepted_at')->label(__('admin.sellers.terms_accepted_at'))
                            ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))->placeholder('—'),
                    ]),
            ]);
    }
}
