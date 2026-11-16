<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_on_site')
                ->label(__('admin.view_on_site'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Category $record): string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (Category $record): bool => $record->is_active),
            // دسته‌ای که زیردسته دارد حذف نمی‌شود
            DeleteAction::make()
                ->visible(fn (Category $record): bool => ! $record->children()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return CategoryResource::getUrl('index');
    }
}
