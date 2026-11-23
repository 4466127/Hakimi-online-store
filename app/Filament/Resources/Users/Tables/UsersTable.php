<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Support\Jalali;
use App\Support\Phone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('admin.users.phone'))
                    ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin.users.role'))
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Owner => 'success',
                        UserRole::Manager => 'info',
                        UserRole::Staff => 'warning',
                        UserRole::Editor => 'gray',
                        UserRole::Courier => 'primary',
                    }),
                IconColumn::make('is_active')
                    ->label(__('admin.users.is_active'))
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('admin.users.last_login_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->placeholder(__('admin.users.never_logged_in')),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('admin.users.empty'));
    }
}
