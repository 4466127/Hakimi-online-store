<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentEvents\Pages;

use App\Filament\Resources\PaymentEvents\PaymentEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentEvents extends ListRecords
{
    protected static string $resource = PaymentEventResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('admin.payment_events.help');
    }
}
