<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.pages.title'))
                    ->state(fn (Page $record): string => $record->tr('title')),
                TextColumn::make('slug')
                    ->label(__('admin.pages.slug'))
                    ->prefix('/page/')
                    ->color('gray'),
                IconColumn::make('has_body')
                    ->label(__('admin.pages.has_body'))
                    ->state(fn (Page $record): bool => $record->hasBody())
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('admin.pages.is_active'))
                    ->boolean(),
                IconColumn::make('show_in_footer')
                    ->label(__('admin.pages.show_in_footer'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('admin.updated_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state)),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordActions([
                Action::make('view_on_site')
                    ->label(__('admin.view_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record): bool => $record->is_active),
                EditAction::make(),
            ])
            ->emptyStateHeading(__('admin.pages.empty'));
    }
}
