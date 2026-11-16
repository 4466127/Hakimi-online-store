<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Models\Attribute;
use App\Models\Category;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * مشخصه‌های یک دسته (در صفحه‌ی ویرایش دسته). زیردسته‌ها این مشخصه‌ها را به ارث می‌برند؛
 * اینجا فقط آن‌هایی که مستقیم به همین دسته داده شده دیده و عوض می‌شود.
 */
class CategoryAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'catalogAttributes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('admin.attributes.for_category');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('is_required')->label(__('admin.attributes.is_required')),
            TextInput::make('position')->label(__('admin.categories.position'))->numeric()->integer()->minValue(0)->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Category $category */
        $category = $this->getOwnerRecord();
        $inherited = $category->parent_id
            ? $category->effectiveAttributes()->reject(fn (Attribute $a): bool => (int) $a->via_category_id === $category->id)
                ->map(fn (Attribute $a): string => $a->tr('name'))->implode('، ')
            : '';

        return $table
            ->description(__('admin.attributes.for_category_help').($inherited !== '' ? ' '.__('admin.attributes.inherited', ['list' => $inherited]) : ''))
            ->recordTitle(fn (Attribute $record): string => $record->tr('name'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.attributes.name'))
                    ->state(fn (Attribute $record): string => $record->tr('name'))
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label(__('admin.attributes.type'))
                    ->formatStateUsing(fn (Attribute $record): string => $record->typeLabel())
                    ->badge()->color('gray'),
                IconColumn::make('is_variant')->label(__('admin.attributes.is_variant_short'))->boolean(),
                IconColumn::make('is_required')->label(__('admin.attributes.is_required'))->boolean(),
                TextColumn::make('position')->label(__('admin.categories.position'))->alignCenter(),
            ])
            ->defaultSort('category_attribute.position')
            ->headerActions([
                AttachAction::make()
                    ->label(__('admin.attributes.attach'))
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('attributes.is_active', true)->orderBy('attributes.position'))
                    ->recordTitle(fn (Attribute $record): string => $record->tr('name'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_required')->label(__('admin.attributes.is_required')),
                        TextInput::make('position')->label(__('admin.categories.position'))->numeric()->integer()->minValue(0)->default(0),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()->label(__('admin.attributes.detach')),
            ])
            ->emptyStateHeading(__('admin.attributes.none_for_category'));
    }
}
