<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductFormData;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_on_site')
                ->label(__('admin.products.view_on_site'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => $this->getRecord()->url(), shouldOpenInNewTab: true)
                // جنس پیش‌نویس و غیرفعال را فقط مدیر پنل می‌بیند (پیش‌نمایش)
                ->visible(fn (): bool => ! $this->getRecord()->trashed()),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** نوع‌ها، مشخصات، عکس‌ها و جنس مرتبط در فورم از جدول‌های خودشان پر می‌شوند. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();

        return [...$data, ...ProductFormData::forForm($product)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        return ProductFormData::persist($record, $data);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return (string) __('admin.products.saved');
    }

    /** بعد از ذخیره در همین صفحه می‌ماند (لیست عکس و نوع‌ها تازه شود). */
    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function afterSave(): void
    {
        $this->fillForm();
    }
}
