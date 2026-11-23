<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * سفارش‌های سایت در پنل (مرحله‌ی ۲-۳): دیدن، تأیید، رد، پیش بردن تا «تحویل شد».
 *
 * سفارش از پنل ساخته یا پاک نمی‌شود (`OrderPolicy`): سند مالی است و اشتباه با «لغو» و دلیل
 * ثبت می‌شود. تغییر وضعیت فقط از `OrderWorkflow` ← `OrderStateMachine` می‌گذرد.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.orders');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.orders.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.orders.plural');
    }

    /** تعداد سفارش‌های «در انتظار تأیید» کنار نام منو. */
    public static function getNavigationBadge(): ?string
    {
        try {
            // از همان پرس‌وجوی محدودشده می‌خوانیم تا پیک تعداد سفارش‌های دیگران را هم نبیند
            $count = static::getEloquentQuery()->where('status', Order::STATUS_PENDING)->count();
        } catch (Throwable $e) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * پیک فقط سفارش‌های واگذارشده‌ی خودش را در لیست می‌بیند (مرحله‌ی ۳؛ `OrderPolicy` هم همین را می‌گوید).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user instanceof User && $user->role->isCourier()) {
            $query->whereHas('sellerOrders', fn (Builder $inner) => $inner->where('assigned_courier_id', $user->id));
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
