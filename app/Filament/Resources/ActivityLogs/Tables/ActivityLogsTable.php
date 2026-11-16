<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'customer']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.activity.when'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->sortable(),
                TextColumn::make('actor')
                    ->label(__('admin.activity.who'))
                    ->state(fn (ActivityLog $record): ?string => $record->actorName())
                    ->placeholder(__('admin.activity.system'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->where(fn (Builder $u) => $u->where('actor_type', 'user')->whereHas('user', fn (Builder $w) => $w->where('name', 'like', "%{$search}%")))
                        ->orWhere(fn (Builder $c) => $c->where('actor_type', 'customer')->whereHas('customer', fn (Builder $w) => $w->where('name', 'like', "%{$search}%")))
                    )),
                TextColumn::make('action')
                    ->label(__('admin.activity.what'))
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->actionLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject_label')
                    ->label(__('admin.activity.subject'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('ip')
                    ->label(__('admin.activity.ip'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('details')
                    ->label(__('admin.activity.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (ActivityLog $record): bool => ! empty($record->changes))
                    ->modalHeading(fn (ActivityLog $record): string => $record->actionLabel())
                    ->modalContent(fn (ActivityLog $record) => view('filament.partials.activity-details', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.close')),
            ])
            ->emptyStateHeading(__('admin.activity.empty'));
    }
}
