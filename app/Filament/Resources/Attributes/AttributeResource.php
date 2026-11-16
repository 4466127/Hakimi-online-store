<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مشخصه‌های کالا (سایز، رنگ، RAM، …) با گزینه‌هایشان. اینکه هر دسته کدام مشخصه‌ها را دارد،
 * در صفحه‌ی همان دسته («مشخصه‌های این دسته») تعیین می‌شود.
 */
class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.catalog');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.attributes.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.attributes.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextInput::make('name.fa')
                        ->label(__('admin.attributes.name'))
                        ->placeholder(__('admin.attributes.name_placeholder'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('code')
                        ->label(__('admin.attributes.code'))
                        ->helperText(__('admin.attributes.code_help'))
                        ->placeholder('shoe_size')
                        ->required()
                        ->maxLength(50)
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->unique(ignoreRecord: true)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('type')
                        ->label(__('admin.attributes.type'))
                        ->options((array) __('admin.attributes.types'))
                        ->default('select')
                        ->required()
                        ->live(),
                    TextInput::make('unit')
                        ->label(__('admin.attributes.unit'))
                        ->placeholder(__('admin.attributes.unit_placeholder'))
                        ->maxLength(20),
                    TextInput::make('position')
                        ->label(__('admin.categories.position'))
                        ->numeric()->integer()->minValue(0)->default(0),
                    Toggle::make('is_active')->label(__('admin.attributes.is_active'))->default(true)->inline(false),
                    Toggle::make('is_variant')
                        ->label(__('admin.attributes.is_variant'))
                        ->helperText(__('admin.attributes.is_variant_help'))
                        ->visible(fn (Get $get): bool => $get('type') === 'select'),
                    Toggle::make('is_filterable')
                        ->label(__('admin.attributes.is_filterable'))
                        ->helperText(__('admin.attributes.is_filterable_help')),
                    Toggle::make('show_in_specs')
                        ->label(__('admin.attributes.show_in_specs'))
                        ->helperText(__('admin.attributes.show_in_specs_help'))
                        ->default(true),
                ]),

            Section::make(__('admin.attributes.options'))
                ->description(__('admin.attributes.options_help'))
                ->visible(fn (Get $get): bool => in_array($get('type'), Attribute::OPTION_TYPES, true))
                ->schema([
                    Repeater::make('options')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('position')
                        ->reorderableWithButtons()
                        ->columns(3)
                        ->addActionLabel(__('admin.attributes.add_option'))
                        ->schema([
                            TextInput::make('value.fa')
                                ->label(__('admin.attributes.option_value'))
                                ->placeholder(__('admin.attributes.option_value_placeholder'))
                                ->required()
                                ->maxLength(100),
                            TextInput::make('slug')
                                ->label(__('admin.attributes.option_slug'))
                                ->helperText(__('admin.attributes.option_slug_help'))
                                ->maxLength(100)
                                ->extraInputAttributes(['dir' => 'ltr']),
                            ColorPicker::make('color_hex')
                                ->label(__('admin.attributes.option_color'))
                                ->helperText(__('admin.attributes.option_color_help')),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['options', 'categories']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.attributes.name'))
                    ->state(fn (Attribute $record): string => $record->tr('name').($record->unit ? ' ('.$record->unit.')' : ''))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%")),
                TextColumn::make('code')->label(__('admin.attributes.code'))->color('gray'),
                TextColumn::make('type')
                    ->label(__('admin.attributes.type'))
                    ->formatStateUsing(fn (Attribute $record): string => $record->typeLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('options_count')->label(__('admin.attributes.options_count'))->alignCenter(),
                TextColumn::make('categories_count')->label(__('admin.attributes.categories_count'))->alignCenter(),
                IconColumn::make('is_variant')->label(__('admin.attributes.is_variant_short'))->boolean(),
                IconColumn::make('is_filterable')->label(__('admin.attributes.is_filterable'))->boolean(),
                IconColumn::make('is_active')->label(__('admin.attributes.is_active'))->boolean(),
            ])
            ->defaultSort('position')
            ->recordActions([EditAction::make()])
            ->emptyStateHeading(__('admin.attributes.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
