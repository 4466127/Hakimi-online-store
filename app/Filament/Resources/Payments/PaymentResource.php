<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * پرداخت‌ها (مرحله‌ی ۳): پول‌های گرفته‌شده و منتظر، رسیدها و «پول دست پیک».
 *
 * اینجا هیچ پولی دستی «پرداخت‌شده» نمی‌شود؛ ثبت پول فقط از صفحه‌ی خود سفارش و با **کد تحویل**
 * انجام می‌شود (`CashCollectionService`). در این بخش فقط دیدن، گزارش، رسید چاپی و
 * «تحویل گرفتن پول از پیک» هست.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.orders');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.payments.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.payments.plural');
    }

    /** پیک فقط پول‌هایی را می‌بیند که خودش گرفته. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User && $user->role->isCourier()) {
            $query->where('collected_by', $user->id);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
