<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Support\Jalali;
use App\Support\Num;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * قاعده‌ی کمیسیون مخصوص یک جنس (در صفحه‌ی ویرایش جنس؛ فقط اجناس فروشندگان، فقط صاحب و مدیر).
 * اولویت: بالاتر از نرخ دسته و نرخ عادی فروشنده؛ فقط «استثنا»ی فروشنده بر آن غلبه می‌کند.
 */
class CommissionRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRates';

    /** نام دری روی دکمه‌ها («ایجاد نرخ کمیسیون …»)، نه نام انگلیسی مدل. */
    protected static function getModelLabel(): ?string
    {
        return (string) __('admin.finance.product_rate_single');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.finance.product_rates');
    }

    /** جنس خود بازارک کمیسیون ندارد؛ کارمند و ویرایشگر این بخش را نمی‌بینند. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product
            && $ownerRecord->seller_id !== null
            && (bool) auth()->user()?->role->canManageSettings();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label(__('admin.finance.type_label'))
                ->options((array) __('commission.types'))
                ->default(ProductCommissionRate::TYPE_PERCENT)
                ->selectablePlaceholder(false)
                ->live()
                ->required(),
            TextInput::make('rate')
                ->label(__('admin.finance.rate'))
                ->numeric()->minValue(0)->maxValue(50)->step('0.01')
                ->suffix('٪')
                ->default(0)
                ->visible(fn (Get $get): bool => $get('type') !== ProductCommissionRate::TYPE_FIXED)
                ->required(fn (Get $get): bool => $get('type') !== ProductCommissionRate::TYPE_FIXED),
            TextInput::make('fixed_amount')
                ->label(__('admin.finance.fixed_amount'))
                ->helperText(__('admin.finance.fixed_amount_help'))
                ->numeric()->minValue(0)->step('0.01')
                ->visible(fn (Get $get): bool => $get('type') === ProductCommissionRate::TYPE_FIXED)
                ->required(fn (Get $get): bool => $get('type') === ProductCommissionRate::TYPE_FIXED),
            Toggle::make('is_active')
                ->label(__('admin.finance.is_active'))
                ->default(true),
            DateTimePicker::make('starts_at')
                ->label(__('admin.finance.starts_at'))
                ->default(now()),
            DateTimePicker::make('ends_at')
                ->label(__('admin.finance.ends_at'))
                ->helperText(__('admin.finance.ends_at_help'))
                ->after('starts_at'),
            TextInput::make('note')
                ->label(__('admin.finance.note'))
                ->maxLength(255)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('admin.finance.product_rates_help'))
            ->columns([
                TextColumn::make('rate')
                    ->label(__('admin.finance.rate'))
                    ->state(fn (ProductCommissionRate $record): string => $record->type === ProductCommissionRate::TYPE_FIXED
                        ? Num::format((string) $record->fixed_amount, 0).' '.__('admin.finance.afn').' / '.__('admin.finance.per_unit')
                        : Num::format((string) $record->rate, 2).'٪')
                    ->weight('bold'),
                TextColumn::make('starts_at')
                    ->label(__('admin.finance.starts_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::date($state))
                    ->placeholder('—'),
                TextColumn::make('ends_at')
                    ->label(__('admin.finance.ends_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::date($state))
                    ->placeholder('∞'),
                IconColumn::make('active')
                    ->label(__('admin.finance.active'))
                    ->state(fn (ProductCommissionRate $record): bool => $record->is_active
                        && ($record->starts_at === null || $record->starts_at->isPast())
                        && ($record->ends_at === null || $record->ends_at->isFuture()))
                    ->boolean(),
                TextColumn::make('note')
                    ->label(__('admin.finance.note'))
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [...self::normalise($data), 'created_by' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::normalise($data)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('—');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalise(array $data): array
    {
        if (($data['type'] ?? ProductCommissionRate::TYPE_PERCENT) === ProductCommissionRate::TYPE_FIXED) {
            return [...$data, 'rate' => 0];
        }

        return [...$data, 'type' => ProductCommissionRate::TYPE_PERCENT, 'fixed_amount' => null];
    }
}
