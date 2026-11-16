<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use App\Support\Money;
use App\Support\Num;
use App\Support\Persian;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('admin.products.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'brand', 'seller', 'images'])->withCount('variants'))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (Product $record): ?string => $record->primaryImage()?->url(300))
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->label(__('admin.products.name'))
                    ->state(fn (Product $record): string => $record->tr('name'))
                    ->description(fn (Product $record): string => $record->category?->tr('name') ?? '')
                    ->weight('bold')
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('search_text', 'like', '%'.Persian::normalize($search).'%')),
                TextColumn::make('brand')
                    ->label(__('admin.products.brand'))
                    ->state(fn (Product $record): ?string => $record->brand?->name)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('price')
                    ->label(__('admin.products.price'))
                    ->state(fn (Product $record): string => self::priceText($record))
                    ->description(fn (Product $record): ?string => $record->variants_count > 1 ? Num::toFa((string) $record->variants_count).' '.__('admin.products.variants_count') : null)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('min_price', $direction)),
                TextColumn::make('total_stock')
                    ->label(__('admin.products.stock'))
                    ->state(fn (Product $record): string => $record->isInStock() ? Num::format((string) $record->total_stock, 0) : (string) __('admin.products.out_of_stock'))
                    ->badge()
                    ->color(fn (Product $record): string => $record->isInStock() ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.products.status'))
                    ->formatStateUsing(fn (Product $record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state): string => Product::STATUS_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('owner')
                    ->label(__('admin.products.owner'))
                    ->state(fn (Product $record): string => $record->seller?->store_name ?: (string) __('admin.products.seller_own'))
                    ->toggleable(),
                IconColumn::make('is_featured')->label(__('admin.products.is_featured'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label(__('admin.products.status'))
                    ->options((array) __('admin.products.statuses')),
                SelectFilter::make('category_id')
                    ->label(__('admin.products.filters.category'))
                    ->options(fn (): array => CategoryForm::parentOptions(null, true))
                    ->searchable()
                    // دسته‌ی انتخاب‌شده و همه‌ی زیردسته‌هایش
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('category_id', [(int) $data['value'], ...(Category::query()->find($data['value'])?->descendantIds() ?? [])])
                        : $query),
                SelectFilter::make('brand_id')
                    ->label(__('admin.products.filters.brand'))
                    ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('owner')
                    ->label(__('admin.products.filters.owner'))
                    ->options(['own' => __('admin.products.filters.owner_own'), 'sellers' => __('admin.products.filters.owner_sellers')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'own' => $query->whereNull('seller_id'),
                        'sellers' => $query->whereNotNull('seller_id'),
                        default => $query,
                    }),
                Filter::make('out_of_stock')
                    ->label(__('admin.products.filters.stock_out'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('total_stock', '<=', 0)),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label(__('admin.products.bulk_activate'))
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('admin.products.bulk_activate_confirm'))
                        ->action(function (Collection $records): void {
                            $service = app(ProductService::class);
                            $done = 0;
                            $skipped = 0;
                            foreach ($records as $product) {
                                $service->setStatus($product, 'active') ? $done++ : $skipped++;
                            }
                            Notification::make()->title(__('admin.products.bulk_activated', ['count' => Num::toFa($done)]))->success()->send();
                            if ($skipped > 0) {
                                Notification::make()->title(__('admin.products.bulk_skipped', ['count' => Num::toFa($skipped)]))->warning()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label(__('admin.products.bulk_deactivate'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription(__('admin.products.bulk_deactivate_confirm'))
                        ->action(function (Collection $records): void {
                            $service = app(ProductService::class);
                            foreach ($records as $product) {
                                $service->setStatus($product, 'inactive');
                            }
                            Notification::make()->title(__('admin.products.bulk_deactivated', ['count' => Num::toFa($records->count())]))->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading(__('admin.products.empty'))
            ->emptyStateDescription(__('admin.products.empty_description'));
    }

    private static function priceText(Product $product): string
    {
        if ($product->min_price === null) {
            return '';
        }
        if ($product->max_price === null || Money::compare($product->max_price, $product->min_price) === 0) {
            return Money::format($product->min_price);
        }

        return (string) __('admin.products.price_range', ['min' => Money::format($product->min_price), 'max' => Money::format($product->max_price)]);
    }
}
