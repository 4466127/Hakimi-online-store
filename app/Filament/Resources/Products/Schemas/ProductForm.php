<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\SellerStatus;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\Catalog\ProductImageService;
use App\Support\Persian;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * فورم جنس: معلومات، عکس‌ها، نوع‌ها (قیمت، موجودی، سایز/رنگ)، مشخصات، توضیحات، جنس مرتبط، آدرس و گوگل.
 *
 * سایز، رنگ و مشخصات از «مشخصه‌های دسته» می‌آید (داده است، نه کد): با عوض شدن دسته، فورم
 * خودکار گزینه‌های تازه را نشان می‌دهد.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            self::basicSection(),
            self::imagesSection(),
            self::variantsSection(),
            self::specsSection(),
            self::descriptionSection(),
            self::relatedSection(),
            self::seoSection(),
        ]);
    }

    private static function basicSection(): Section
    {
        return Section::make(__('admin.products.section_basic'))
            ->columns(2)
            ->schema([
                TextInput::make('name.fa')
                    ->label(__('admin.products.name'))
                    ->placeholder(__('admin.products.name_placeholder'))
                    ->required()
                    ->maxLength(200)
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->label(__('admin.products.category'))
                    ->helperText(__('admin.products.category_help'))
                    ->options(fn (): array => CategoryForm::parentOptions(null, true))
                    ->searchable()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Select::make('brand_id')
                    ->label(__('admin.products.brand'))
                    ->helperText(__('admin.products.brand_help'))
                    ->relationship('brand', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                    ->searchable()
                    ->preload(),
                Select::make('seller_id')
                    ->label(__('admin.products.seller'))
                    ->helperText(__('admin.products.seller_help'))
                    ->options(fn (): array => Seller::query()
                        ->where('status', SellerStatus::Approved->value)
                        ->orderBy('store_name')
                        ->pluck('store_name', 'id')
                        ->all())
                    ->placeholder(__('admin.products.seller_own'))
                    ->searchable(),
                ToggleButtons::make('status')
                    ->label(__('admin.products.status'))
                    ->helperText(__('admin.products.status_help'))
                    ->options((array) __('admin.products.statuses'))
                    ->colors(Product::STATUS_COLORS)
                    ->inline()
                    ->default('draft')
                    ->required(),
                Toggle::make('is_featured')
                    ->label(__('admin.products.is_featured'))
                    ->helperText(__('admin.products.is_featured_help'))
                    ->inline(false),
            ]);
    }

    private static function imagesSection(): Section
    {
        return Section::make(__('admin.products.section_images'))
            ->description(__('admin.products.images_help'))
            ->collapsible()
            ->schema([
                Repeater::make('existing_images')
                    ->label(__('admin.products.existing_images'))
                    ->addable(false)
                    ->reorderableWithButtons()
                    ->collapsible(false)
                    ->itemLabel(null)
                    ->columns(['default' => 1, 'sm' => 2])
                    ->visible(fn (Get $get): bool => ! empty($get('existing_images')))
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('url'),
                        Image::make(fn (Get $get): string => (string) $get('url'), fn (Get $get): string => (string) $get('alt'))
                            ->imageSize('6rem'),
                        TextInput::make('alt')
                            ->label(__('admin.products.image_alt'))
                            ->placeholder(__('admin.products.image_alt_placeholder'))
                            ->maxLength(200),
                    ]),
                FileUpload::make('new_images')
                    ->label(__('admin.products.new_images'))
                    ->helperText(__('admin.products.new_images_help'))
                    ->multiple()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxFiles(ProductImageService::MAX_IMAGES)
                    ->maxSize(10240)
                    ->reorderable()
                    ->disk('local')
                    ->directory('tmp/product-uploads')
                    ->visibility('private')
                    ->panelLayout('grid'),
            ]);
    }

    private static function variantsSection(): Section
    {
        return Section::make(__('admin.products.section_variants'))
            ->description(__('admin.products.variants_help'))
            ->schema([
                Actions::make([self::generateAction()])->key('generate_actions'),
                Repeater::make('variants')
                    ->hiddenLabel()
                    ->minItems(1)
                    ->required()
                    ->default([['is_active' => true, 'stock_qty' => 0]])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->addActionLabel(__('admin.products.variant_add'))
                    ->itemLabel(fn (array $state): ?string => self::variantTitle($state))
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema([
                        Hidden::make('id'),
                        Select::make('option_ids')
                            ->label(__('admin.products.variant_options'))
                            ->helperText(__('admin.products.variant_options_help'))
                            ->multiple()
                            ->placeholder(__('admin.products.variant_options_placeholder'))
                            ->options(fn (Get $get): array => self::variantOptionGroups((int) $get('../../category_id')))
                            ->columnSpanFull(),
                        TextInput::make('price')
                            ->label(__('admin.products.price'))
                            ->placeholder(__('admin.products.price_placeholder'))
                            ->required()
                            ->inputMode('decimal')
                            ->maxLength(15)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('compare_at_price')
                            ->label(__('admin.products.compare_at_price'))
                            ->helperText(__('admin.products.compare_at_price_help'))
                            ->inputMode('decimal')
                            ->maxLength(15)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('stock_qty')
                            ->label(__('admin.products.stock_qty'))
                            ->default(0)
                            ->required()
                            ->inputMode('decimal')
                            ->maxLength(12)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Toggle::make('is_active')
                            ->label(__('admin.products.variant_is_active'))
                            ->default(true)
                            ->inline(false),
                        Section::make(__('admin.products.variant_more'))
                            ->collapsed()
                            ->compact()
                            ->columns(['default' => 2, 'md' => 4])
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('low_stock_threshold')
                                    ->label(__('admin.products.low_stock_threshold'))
                                    ->helperText(__('admin.products.low_stock_threshold_help'))
                                    ->inputMode('decimal')
                                    ->maxLength(12)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('cost_price')
                                    ->label(__('admin.products.cost_price'))
                                    ->helperText(__('admin.products.cost_price_help'))
                                    ->inputMode('decimal')
                                    ->maxLength(15)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('sku')
                                    ->label(__('admin.products.sku'))
                                    ->helperText(__('admin.products.sku_help'))
                                    ->maxLength(60)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('barcode')
                                    ->label(__('admin.products.barcode'))
                                    ->maxLength(64)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                TextInput::make('weight_grams')
                                    ->label(__('admin.products.weight_grams'))
                                    ->helperText(__('admin.products.weight_grams_help'))
                                    ->numeric()->integer()->minValue(0),
                                Toggle::make('is_default')
                                    ->label(__('admin.products.variant_is_default'))
                                    ->inline(false),
                                Toggle::make('allow_backorder')
                                    ->label(__('admin.products.allow_backorder'))
                                    ->helperText(__('admin.products.allow_backorder_help'))
                                    ->inline(false),
                            ]),
                    ]),
            ]);
    }

    /**
     * «ساختن همه‌ی ترکیب‌ها»: سایزها و رنگ‌های علامت‌خورده ← یک نوع برای هر ترکیب (تکراری ساخته نمی‌شود).
     */
    private static function generateAction(): Action
    {
        return Action::make('generateVariants')
            ->label(__('admin.products.generate'))
            ->icon('heroicon-o-squares-plus')
            ->color('gray')
            ->modalHeading(__('admin.products.generate_modal'))
            ->modalDescription(__('admin.products.generate_help'))
            ->modalSubmitActionLabel(__('admin.products.generate_modal'))
            ->schema(function (Get $get): array {
                $attributes = self::variantAttributes((int) $get('category_id'));
                if ($attributes->isEmpty()) {
                    return [Text::make(__($get('category_id') ? 'admin.products.generate_no_attributes' : 'admin.products.generate_pick_category'))];
                }

                $fields = [];
                foreach ($attributes as $attribute) {
                    $fields[] = CheckboxList::make('opt_'.$attribute->id)
                        ->label($attribute->tr('name'))
                        ->options($attribute->options->where('is_active', true)->mapWithKeys(fn (AttributeOption $o) => [$o->id => $o->tr('value')])->all())
                        ->columns(['default' => 3, 'md' => 6])
                        ->bulkToggleable();
                }
                $fields[] = TextInput::make('price')
                    ->label(__('admin.products.generate_price'))
                    ->inputMode('decimal')
                    ->extraInputAttributes(['dir' => 'ltr']);
                $fields[] = TextInput::make('stock_qty')
                    ->label(__('admin.products.generate_stock'))
                    ->default(0)
                    ->inputMode('decimal')
                    ->extraInputAttributes(['dir' => 'ltr']);

                return $fields;
            })
            ->action(function (array $data, Get $get, Set $set): void {
                $attributes = self::variantAttributes((int) $get('category_id'));
                $existing = (array) $get('variants');

                // ترکیب‌هایی که از قبل هست
                $have = [];
                foreach ($existing as $row) {
                    $ids = array_map('intval', (array) ($row['option_ids'] ?? []));
                    sort($ids);
                    $have[implode('-', $ids)] = true;
                }

                $combos = [[]];
                foreach ($attributes as $attribute) {
                    $picked = array_map('intval', (array) ($data['opt_'.$attribute->id] ?? []));
                    if ($picked === []) {
                        continue;
                    }
                    $next = [];
                    foreach ($combos as $combo) {
                        foreach ($picked as $id) {
                            $next[] = [...$combo, $id];
                        }
                    }
                    $combos = $next;
                }

                $new = [];
                foreach ($combos as $combo) {
                    if ($combo === []) {
                        continue;
                    }
                    $key = $combo;
                    sort($key);
                    if (isset($have[implode('-', $key)])) {
                        continue;
                    }
                    $have[implode('-', $key)] = true;
                    $new[] = [
                        'option_ids' => $combo,
                        'price' => (string) ($data['price'] ?? ''),
                        'stock_qty' => (string) ($data['stock_qty'] ?? 0),
                        'is_active' => true,
                    ];
                }

                if ($new === []) {
                    Notification::make()->title(__('admin.products.generate_nothing'))->warning()->send();

                    return;
                }

                // ردیف خالیِ اولیه (بی سایز و رنگ، بی SKU) جایش را به ترکیب‌های تازه می‌دهد
                $kept = array_filter($existing, fn (array $row) => ! empty($row['id']) || ! empty($row['option_ids']) || ! empty($row['sku']));
                $set('variants', [...array_values($kept), ...$new]);

                Notification::make()->title(__('admin.products.generate_done', ['count' => count($new)]))->success()->send();
            });
    }

    private static function specsSection(): Section
    {
        return Section::make(__('admin.products.section_specs'))
            ->description(__('admin.products.specs_help'))
            ->collapsible()
            ->schema(fn (Get $get): array => self::specFields((int) $get('category_id')));
    }

    /** @return list<Component> */
    private static function specFields(int $categoryId): array
    {
        $category = $categoryId > 0 ? Category::query()->find($categoryId) : null;
        if ($category === null) {
            return [Text::make(__('admin.products.specs_pick_category'))];
        }

        $attributes = $category->effectiveAttributes()->where('is_variant', false);
        if ($attributes->isEmpty()) {
            return [Text::make(__('admin.products.specs_empty'))];
        }

        $fields = [];
        foreach ($attributes as $attribute) {
            $name = 'specs.'.$attribute->id;
            $label = $attribute->tr('name').($attribute->unit ? ' ('.$attribute->unit.')' : '');
            $required = (bool) $attribute->pivot_required;

            $field = match ($attribute->type) {
                'select', 'multiselect' => Select::make($name)
                    ->options(AttributeOption::query()->where('attribute_id', $attribute->id)->where('is_active', true)
                        ->orderBy('position')->get()->mapWithKeys(fn (AttributeOption $o) => [$o->id => $o->tr('value')])->all())
                    ->multiple($attribute->type === 'multiselect')
                    ->searchable(),
                'number' => TextInput::make($name)->inputMode('decimal')->extraInputAttributes(['dir' => 'ltr'])->maxLength(20),
                'boolean' => Toggle::make($name),
                default => TextInput::make($name)->maxLength(255),
            };

            $fields[] = $field->label($label.($required ? ' *' : ''));
        }

        return [Group::make($fields)->columns(['default' => 1, 'md' => 2])];
    }

    private static function descriptionSection(): Section
    {
        return Section::make(__('admin.products.section_description'))
            ->collapsible()
            ->schema([
                Textarea::make('short_description.fa')
                    ->label(__('admin.products.short_description'))
                    ->helperText(__('admin.products.short_description_help'))
                    ->rows(2)
                    ->maxLength(300),
                Textarea::make('description.fa')
                    ->label(__('admin.products.description'))
                    ->helperText(__('admin.products.description_help'))
                    ->rows(8)
                    ->maxLength(10000),
            ]);
    }

    private static function relatedSection(): Section
    {
        return Section::make(__('admin.products.section_related'))
            ->collapsible()
            ->collapsed()
            ->schema([
                Select::make('related')
                    ->label(__('admin.products.related'))
                    ->helperText(__('admin.products.related_help'))
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Product::query()
                        ->where('search_text', 'like', '%'.Persian::normalize($search).'%')
                        ->limit(30)->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->tr('name')])->all())
                    ->getOptionLabelsUsing(fn (array $values): array => Product::query()->whereIn('id', $values)->get()
                        ->mapWithKeys(fn (Product $p) => [$p->id => $p->tr('name')])->all()),
            ]);
    }

    private static function seoSection(): Section
    {
        return Section::make(__('admin.products.section_seo'))
            ->description(__('admin.products.section_seo_help'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make('slug')
                    ->label(__('admin.products.slug'))
                    ->helperText(__('admin.products.slug_help'))
                    ->maxLength(200)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->columnSpanFull(),
                TextInput::make('meta_title.fa')
                    ->label(__('admin.products.meta_title'))
                    ->maxLength(70),
                DateTimePicker::make('published_at')
                    ->label(__('admin.products.published_at'))
                    ->helperText(__('admin.products.published_at_help'))
                    ->seconds(false),
                Textarea::make('meta_description.fa')
                    ->label(__('admin.products.meta_description'))
                    ->rows(2)
                    ->maxLength(160)
                    ->columnSpanFull(),
            ]);
    }

    // ───────────────────────── کمکی ─────────────────────────

    /** مشخصه‌های نوع‌سازِ دسته (سایز، رنگ…) با گزینه‌ها. @return Collection<int, Attribute> */
    private static function variantAttributes(int $categoryId): Collection
    {
        $category = $categoryId > 0 ? Category::query()->find($categoryId) : null;
        if ($category === null) {
            return collect();
        }

        $attributes = $category->effectiveAttributes()->where('is_variant', true)->values();
        $attributes->load('options');

        return $attributes;
    }

    /** @return array<string, array<int, string>> گزینه‌ها دسته‌دسته: «سایز» ← ۴۰، ۴۱… */
    private static function variantOptionGroups(int $categoryId): array
    {
        $groups = [];
        foreach (self::variantAttributes($categoryId) as $attribute) {
            $groups[$attribute->tr('name')] = $attribute->options->where('is_active', true)
                ->mapWithKeys(fn (AttributeOption $o) => [$o->id => $o->tr('value')])->all();
        }

        return $groups;
    }

    /** عنوان کوتاه هر نوع در لیست: «۴۲ / سیاه — ۲٬۵۰۰». @param array<string, mixed> $state */
    private static function variantTitle(array $state): ?string
    {
        $ids = array_map('intval', (array) ($state['option_ids'] ?? []));
        $labels = $ids === [] ? [] : AttributeOption::query()->whereIn('id', $ids)->get()
            ->sortBy('attribute_id')->map(fn (AttributeOption $o) => $o->tr('value'))->all();

        $title = $labels === [] ? null : implode(' / ', $labels);
        $price = trim((string) ($state['price'] ?? ''));

        return $title === null && $price === '' ? null : trim(($title ?? '').($price !== '' ? ' — '.$price : ''));
    }
}
