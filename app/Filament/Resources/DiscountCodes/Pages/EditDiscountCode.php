<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use App\Filament\Resources\DiscountCodes\Schemas\DiscountCodeForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiscountCode extends EditRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DiscountCodeForm::normalise($data);
    }

    protected function getHeaderActions(): array
    {
        // کدی که استفاده شده حذف نمی‌شود (DiscountCodePolicy)؛ خاموشش کنید
        return [DeleteAction::make()];
    }
}
