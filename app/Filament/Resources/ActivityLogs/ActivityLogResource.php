<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * تاریخچه‌ی فعالیت: چه کسی، چه وقت، چه کرد. فقط دیدنی (ActivityLogPolicy).
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'activity';

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.system');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.activity.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.activity.plural');
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
