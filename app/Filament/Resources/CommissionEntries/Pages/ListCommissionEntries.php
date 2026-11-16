<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionEntries\Pages;

use App\Filament\Resources\CommissionEntries\CommissionEntryResource;
use App\Filament\Widgets\MarketplaceFinanceStats;
use Filament\Resources\Pages\ListRecords;

class ListCommissionEntries extends ListRecords
{
    protected static string $resource = CommissionEntryResource::class;

    protected function getHeaderWidgets(): array
    {
        return [MarketplaceFinanceStats::class];
    }
}
