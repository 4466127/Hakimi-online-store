<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\RelationManagers;

use App\Filament\Resources\Settlements\Tables\SettlementsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * تسویه‌های یک فروشنده (فقط دیدن؛ ثبت تسویه با دکمه‌ی «ثبت تسویه» بالای صفحه).
 */
class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.finance.settlements');
    }

    public function table(Table $table): Table
    {
        return SettlementsTable::configure($table, showSeller: false);
    }
}
