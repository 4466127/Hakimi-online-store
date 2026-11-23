<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscountCodes\Schemas;

use App\Models\DiscountCode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * فورم کد تخفیف. همه‌ی متن‌ها «کد تخفیف».
 */
class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.discounts.section_main'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('admin.discounts.code'))
                            ->placeholder('EID1405')
                            ->helperText(__('admin.discounts.code_help'))
                            ->required()
                            ->maxLength(30)
                            ->mutateStateForValidationUsing(fn (?string $state): string => DiscountCode::normaliseCode($state))
                            ->regex('/^[A-Z0-9_-]{3,30}$/')
                            ->validationMessages(['regex' => __('admin.discounts.code_invalid')])
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform:uppercase'])
                            ->autofocus(),
                        Select::make('type')
                            ->label(__('admin.discounts.type'))
                            ->options((array) __('admin.discounts.types'))
                            ->default(DiscountCode::TYPE_PERCENT)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->required(),
                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === DiscountCode::TYPE_FIXED
                                ? (string) __('admin.discounts.value_fixed')
                                : (string) __('admin.discounts.value_percent'))
                            ->placeholder(fn (Get $get): string => $get('type') === DiscountCode::TYPE_FIXED ? '200' : '10')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?int => $get('type') === DiscountCode::TYPE_FIXED ? null : 100)
                            ->step('0.01')
                            ->suffix(fn (Get $get): string => $get('type') === DiscountCode::TYPE_FIXED ? (string) __('admin.finance.afn') : '٪')
                            ->required(),
                        TextInput::make('max_discount')
                            ->label(__('admin.discounts.max_discount'))
                            ->helperText(__('admin.discounts.max_discount_help'))
                            ->placeholder('500')
                            ->numeric()->minValue(0.01)->step('0.01')
                            ->suffix(__('admin.finance.afn'))
                            ->visible(fn (Get $get): bool => $get('type') !== DiscountCode::TYPE_FIXED),
                        TextInput::make('min_order_total')
                            ->label(__('admin.discounts.min_order_total'))
                            ->helperText(__('admin.discounts.min_order_total_help'))
                            ->placeholder('2000')
                            ->numeric()->minValue(0)->step('0.01')
                            ->suffix(__('admin.finance.afn')),
                        TextInput::make('note')
                            ->label(__('admin.discounts.note'))
                            ->placeholder(__('admin.discounts.note_placeholder'))
                            ->maxLength(255),
                    ]),

                Section::make(__('admin.discounts.section_limits'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_uses')
                            ->label(__('admin.discounts.max_uses'))
                            ->helperText(__('admin.discounts.max_uses_help'))
                            ->placeholder('100')
                            ->integer()->minValue(1),
                        TextInput::make('max_uses_per_phone')
                            ->label(__('admin.discounts.max_uses_per_phone'))
                            ->helperText(__('admin.discounts.max_uses_per_phone_help'))
                            ->placeholder('1')
                            ->integer()->minValue(1)
                            ->default(1),
                        DateTimePicker::make('starts_at')
                            ->label(__('admin.discounts.starts_at'))
                            ->helperText(__('admin.discounts.starts_at_help')),
                        DateTimePicker::make('ends_at')
                            ->label(__('admin.discounts.ends_at'))
                            ->helperText(__('admin.discounts.ends_at_help'))
                            ->after('starts_at'),
                        Toggle::make('first_order_only')
                            ->label(__('admin.discounts.first_order_only'))
                            ->helperText(__('admin.discounts.first_order_only_help')),
                        Toggle::make('is_active')
                            ->label(__('admin.discounts.is_active'))
                            ->helperText(__('admin.discounts.is_active_help'))
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * کد یکسان‌شده؛ «سقف تخفیف» فقط برای درصدی.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalise(array $data): array
    {
        $data['code'] = DiscountCode::normaliseCode((string) ($data['code'] ?? ''));
        if (($data['type'] ?? null) === DiscountCode::TYPE_FIXED) {
            $data['max_discount'] = null;
        }

        return $data;
    }
}
