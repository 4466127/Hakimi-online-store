<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductFormData;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    /** سبز «جنس ذخیره شد» با نام جنس؛ دکمه‌ی ویرایش برای وقتی که خواست همان جنس را دوباره باز کند. */
    protected function getCreatedNotification(): ?Notification
    {
        /** @var Product $product */
        $product = $this->getRecord();

        return Notification::make()
            ->success()
            ->title((string) __('admin.products.saved'))
            ->body($product->tr('name').' — '.__('admin.products.saved_next'))
            ->actions([
                Action::make('edit')
                    ->label((string) __('admin.products.edit_saved'))
                    ->button()
                    ->color('gray')
                    ->url(ProductResource::getUrl('edit', ['record' => $product])),
            ]);
    }

    /** بعد از ذخیره‌ی موفق، فورم خالیِ جنس بعدی (مدیر اجناس را پشت سر هم درج می‌کند). */
    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('create');
    }
}
