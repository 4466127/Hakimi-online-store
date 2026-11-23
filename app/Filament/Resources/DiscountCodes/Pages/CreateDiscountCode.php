<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use App\Filament\Resources\DiscountCodes\Schemas\DiscountCodeForm;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscountCode extends CreateRecord
{
    protected static string $resource = DiscountCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...DiscountCodeForm::normalise($data), 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return DiscountCodeResource::getUrl('index');
    }
}
