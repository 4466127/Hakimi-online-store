<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Services\Payments\CashCollectionService;
use App\Support\Money;
use App\Support\Num;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /** بالای لیست: «پول در دست پیک‌ها» (برای تسویه‌ی روزانه). */
    public function getSubheading(): ?string
    {
        $rows = app(CashCollectionService::class)->heldByCourier();
        if ($rows->isEmpty()) {
            return null;
        }

        $lines = $rows->map(fn (array $row): string => (string) __('admin.payments.held_row', [
            'name' => $row['courier']?->name ?? '—',
            'count' => Num::toFa((string) $row['count']),
            'total' => Money::format($row['total']),
        ]))->implode(' • ');

        return __('admin.payments.held_title').': '.$lines;
    }
}
