<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeVideos\Pages;

use App\Filament\Resources\HomeVideos\HomeVideoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeVideo extends EditRecord
{
    protected static string $resource = HomeVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return HomeVideoResource::getUrl('index');
    }
}
