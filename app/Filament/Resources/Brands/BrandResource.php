<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Models\Brand;
use App\Services\ImageStore;
use App\Support\Persian;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * برندهای کالا (سامسونگ، اپل، HP، …). ساختن و ویرایش در پنجره‌ی کوچک (بی صفحه‌ی جدا).
 */
class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.catalog');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.brands.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.brands.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label(__('admin.brands.name'))
                ->placeholder('Samsung')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                    if ($operation === 'create') {
                        $set('slug', Persian::slug($state));
                    }
                }),
            TextInput::make('slug')
                ->label(__('admin.brands.slug'))
                ->helperText(__('admin.categories.slug_help'))
                ->required()
                ->maxLength(150)
                ->mutateStateForValidationUsing(fn (?string $state): string => Persian::slug($state))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
                ->extraInputAttributes(['dir' => 'ltr']),
            FileUpload::make('logo_path')
                ->label(__('admin.brands.logo'))
                ->helperText(__('admin.brands.logo_help'))
                ->image()
                ->disk('public')
                ->directory('brands')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048)
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageStore::class)
                    ->storeAsWebp($file->getRealPath(), 'brands', maxSide: 300, quality: 85))
                ->columnSpanFull(),
            Toggle::make('is_active')->label(__('admin.brands.is_active'))->default(true),
            TextInput::make('position')->label(__('admin.categories.position'))->numeric()->integer()->minValue(0)->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label(__('admin.brands.logo'))->disk('public')->height(32),
                TextColumn::make('name')->label(__('admin.brands.name'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('slug')->label(__('admin.brands.slug'))->color('gray'),
                ToggleColumn::make('is_active')->label(__('admin.brands.is_active')),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('admin.brands.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBrands::route('/'),
        ];
    }
}
