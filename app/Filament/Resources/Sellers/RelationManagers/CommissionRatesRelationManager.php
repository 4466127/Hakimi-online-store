<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sellers\RelationManagers;

use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\SellerCommissionRate;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * نرخ‌های کمیسیون ویژه‌ی یک فروشنده (در صفحه‌ی فروشنده).
 */
class CommissionRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRates';

    /** نام دری روی دکمه‌ها («ایجاد نرخ کمیسیون …»)، نه نام انگلیسی مدل. */
    protected static function getModelLabel(): ?string
    {
        return (string) __('admin.finance.seller_rate_single');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.finance.rates');
    }

    /** در صفحه‌ی «دیدن» هم ساختن و ویرایش ممکن باشد. */
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
                ->default(SellerCommissionRate::TYPE_PERCENT)
                ->selectablePlaceholder(false)
                ->live()
                ->required(),
            TextInput::make('rate')
                ->label(__('admin.finance.rate'))
                ->numeric()->minValue(0)->maxValue(50)->step('0.01')
                ->suffix('٪')
                ->default(0)
                ->visible(fn (Get $get): bool => $get('type') !== SellerCommissionRate::TYPE_FIXED)
                ->required(fn (Get $get): bool => $get('type') !== SellerCommissionRate::TYPE_FIXED),
            TextInput::make('fixed_amount')
                ->label(__('admin.finance.fixed_amount'))
                ->helperText(__('admin.finance.fixed_amount_help'))
                ->numeric()->minValue(0)->step('0.01')
                ->visible(fn (Get $get): bool => $get('type') === SellerCommissionRate::TYPE_FIXED)
                ->required(fn (Get $get): bool => $get('type') === SellerCommissionRate::TYPE_FIXED),
            Toggle::make('is_exception')
                ->label(__('admin.finance.is_exception'))
                ->helperText(__('admin.finance.is_exception_help'))
                ->default(false),
            Toggle::make('is_active')
                ->label(__('admin.finance.is_active'))
                ->default(true),
            Select::make('category_id')
                ->label(__('admin.finance.rate_category'))
                ->helperText(__('admin.finance.rate_category_help'))
                ->options(fn (): array => CategoryForm::parentOptions(null, allLevels: true))
                ->searchable()
                ->placeholder(__('admin.finance.all_categories')),
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
            ->description(__('admin.finance.rates_help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('category'))
            ->columns([
                TextColumn::make('rate')
                    ->label(__('admin.finance.rate'))
                    ->state(fn (SellerCommissionRate $record): string => $record->type === SellerCommissionRate::TYPE_FIXED
                        ? Num::format((string) $record->fixed_amount, 0).' '.__('admin.finance.afn').' / '.__('admin.finance.per_unit')
                        : Num::format((string) $record->rate, 2).'٪')
                    ->weight('bold'),
                IconColumn::make('is_exception')
                    ->label(__('admin.finance.exception_short'))
                    ->boolean(),
                TextColumn::make('category')
                    ->label(__('admin.finance.rate_category'))
                    ->state(fn (SellerCommissionRate $record): string => $record->category?->tr('name') ?? __('admin.finance.all_categories')),
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
                    ->state(fn (SellerCommissionRate $record): bool => $record->is_active
                        && ($record->starts_at === null || $record->starts_at->isPast())
                        && ($record->ends_at === null || $record->ends_at->isFuture()))
                    ->boolean(),
                TextColumn::make('reason')
                    ->label(__('admin.finance.reason_label'))
                    ->state(fn (SellerCommissionRate $record): string => $record->reasonLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('note')
                    ->label(__('admin.finance.note'))
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [...self::normalise($data), 'reason' => 'manual', 'created_by' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::normalise($data)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('—');
    }

    /**
     * نوع ثابت ← درصد ۰؛ نوع درصدی ← مبلغ ثابت خالی (تا قاعده دوپهلو نباشد).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalise(array $data): array
    {
        if (($data['type'] ?? SellerCommissionRate::TYPE_PERCENT) === SellerCommissionRate::TYPE_FIXED) {
            return [...$data, 'rate' => 0];
        }

        return [...$data, 'type' => SellerCommissionRate::TYPE_PERCENT, 'fixed_amount' => null];
    }
}
