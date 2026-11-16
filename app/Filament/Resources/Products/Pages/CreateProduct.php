<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductFormData;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** ذخیره با قاعده‌های ProductService (نوع‌ها، آدرس، cache، عکس). */
    protected function handleRecordCreation(array $data): Model
    {
        return ProductFormData::persist(null, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return (string) __('admin.products.saved');
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
