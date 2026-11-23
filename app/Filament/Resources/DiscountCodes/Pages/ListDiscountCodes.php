<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\Pages;

use App\Filament\Resources\DiscountCodes\DiscountCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiscountCodes extends ListRecords
{
    protected static string $resource = DiscountCodeResource::class;

    public function getTitle(): string
    {
        return (string) __('admin.discounts.nav');
    }

    public function getSubheading(): ?string
    {
        return (string) __('admin.discounts.help');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
