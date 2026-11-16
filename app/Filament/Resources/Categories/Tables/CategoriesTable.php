<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * لیست دسته‌ها به ترتیب درختی: هر دسته‌ی اصلی، زیرش گروه‌هایش، زیر هر گروه زیردسته‌هایش.
 */
class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('admin.categories.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => self::treeOrder($query)->withCount('children'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.categories.name'))
                    ->state(fn (Category $record): string => $record->tr('name'))
                    ->icon(fn (Category $record): ?string => $record->depth === 0 ? ($record->iconName() ?? 'heroicon-o-folder') : null)
                    ->weight(fn (Category $record): ?string => $record->depth === 0 ? 'bold' : null)
                    // تورفتگی به اندازه‌ی سطح
                    ->extraAttributes(fn (Category $record): array => ['style' => 'padding-inline-start:'.($record->depth * 1.75).'rem'])
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('categories.name', 'like', "%{$search}%")),
                TextColumn::make('depth')
                    ->label(__('admin.categories.level'))
                    ->formatStateUsing(fn (int $state): string => (string) (__('admin.categories.levels')[$state] ?? $state))
                    ->badge()
                    ->color(fn (int $state): string => ['primary', 'warning', 'gray'][$state] ?? 'gray'),
                TextColumn::make('children_count')
                    ->label(__('admin.categories.children_count'))
                    ->alignCenter()
                    ->color('gray'),
                ToggleColumn::make('is_active')
                    ->label(__('admin.categories.is_active')),
                ToggleColumn::make('show_in_menu')
                    ->label(__('admin.categories.show_in_menu')),
                TextColumn::make('position')
                    ->label(__('admin.categories.position'))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('root')
                    ->label(__('admin.categories.filter_parent'))
                    ->options(fn (): array => Category::query()->roots()->orderBy('position')->get()
                        ->mapWithKeys(fn (Category $c): array => [$c->id => $c->tr('name')])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $id = (int) ($data['value'] ?? 0);
                        if ($id === 0) {
                            return $query;
                        }

                        return $query->where(fn (Builder $q) => $q
                            ->where('categories.id', $id)
                            ->orWhere('categories.path', (string) $id)
                            ->orWhere('categories.path', 'like', $id.'/%'));
                    }),
            ])
            ->recordActions([
                Action::make('view_on_site')
                    ->label(__('admin.view_on_site'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Category $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Category $record): bool => $record->is_active),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Category $record): bool => $record->children_count === 0),
            ])
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->emptyStateHeading(__('admin.categories.empty'));
    }

    /**
     * ترتیب درختی با دو join (درخت حداکثر سه سطح است):
     * دسته‌ی اصلی ← گروه‌هایش ← زیردسته‌های هر گروه، هر سطح به ترتیب «ترتیب».
     */
    private static function treeOrder(Builder $query): Builder
    {
        return $query
            ->select('categories.*')
            ->leftJoin('categories as p1', 'p1.id', '=', 'categories.parent_id')
            ->leftJoin('categories as p2', 'p2.id', '=', 'p1.parent_id')
            ->reorder()
            ->orderByRaw('CASE categories.depth WHEN 0 THEN categories.position WHEN 1 THEN p1.position ELSE p2.position END')
            ->orderByRaw('CASE categories.depth WHEN 0 THEN categories.id WHEN 1 THEN p1.id ELSE p2.id END')
            ->orderByRaw('CASE categories.depth WHEN 0 THEN -1 WHEN 1 THEN categories.position ELSE p1.position END')
            ->orderByRaw('CASE categories.depth WHEN 0 THEN 0 WHEN 1 THEN categories.id ELSE p1.id END')
            ->orderByRaw('CASE categories.depth WHEN 2 THEN categories.position ELSE -1 END')
            ->orderBy('categories.id');
    }
}
