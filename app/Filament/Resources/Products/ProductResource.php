<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * اجناس. فورم با ProductService ذخیره می‌شود (نوع‌ها، آدرس و ریدایرکت، cache، عکس WebP).
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.catalog');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.products.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.products.plural');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Product ? $record->tr('name') : null;
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /** سطل زباله هم قابل دیدن است (فیلتر «حذف‌شده‌ها»). */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
