<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\Tables;

use App\Models\DiscountCode;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\Num;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiscountCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.discounts.code'))
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('value')
                    ->label(__('admin.discounts.amount'))
                    ->state(fn (DiscountCode $record): string => $record->isPercent()
                        ? Num::format((string) $record->value, 2).'٪'
                            .($record->max_discount !== null ? ' — '.__('admin.discounts.up_to', ['amount' => Money::format($record->max_discount)]) : '')
                        : Money::format($record->value)),
                TextColumn::make('min_order_total')
                    ->label(__('admin.discounts.min_order_total'))
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('used_count')
                    ->label(__('admin.discounts.used'))
                    ->state(fn (DiscountCode $record): string => Num::toFa((string) $record->used_count)
                        .($record->max_uses !== null ? ' / '.Num::toFa((string) $record->max_uses) : '')),
                TextColumn::make('ends_at')
                    ->label(__('admin.discounts.ends_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->placeholder('∞'),
                IconColumn::make('first_order_only')
                    ->label(__('admin.discounts.first_order_short'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('admin.discounts.status'))
                    ->badge()
                    ->state(fn (DiscountCode $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => (string) __('admin.discounts.statuses.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.discounts.is_active')),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make()])
            ->emptyStateHeading(__('admin.discounts.empty'));
    }

    /** active | scheduled | expired | exhausted | off */
    public static function status(DiscountCode $code): string
    {
        return match (true) {
            ! $code->is_active => 'off',
            $code->ends_at !== null && $code->ends_at->isPast() => 'expired',
            $code->max_uses !== null && $code->used_count >= $code->max_uses => 'exhausted',
            $code->starts_at !== null && $code->starts_at->isFuture() => 'scheduled',
            default => 'active',
        };
    }
}
