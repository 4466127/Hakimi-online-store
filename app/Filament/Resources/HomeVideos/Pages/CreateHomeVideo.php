<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeVideos\Pages;

use App\Filament\Resources\HomeVideos\HomeVideoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeVideo extends CreateRecord
{
    protected static string $resource = HomeVideoResource::class;

    protected function getRedirectUrl(): string
    {
        return HomeVideoResource::getUrl('index');
    }
}
