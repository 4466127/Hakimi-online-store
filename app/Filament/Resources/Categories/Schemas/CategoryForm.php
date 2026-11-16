<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Services\ImageStore;
use App\Support\Persian;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.categories.section_main'))
                    ->description(__('admin.categories.help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name.fa')
                            ->label(__('admin.categories.name'))
                            ->placeholder(__('admin.categories.name_placeholder'))
                            ->required()
                            ->maxLength(100)
                            ->autofocus()
                            ->live(onBlur: true)
                            // آدرس فقط هنگام ساختن خودکار از نام ساخته می‌شود (بعداً لینک‌ها نشکند)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Persian::slug($state));
                                }
                            }),
                        Select::make('parent_id')
                            ->label(__('admin.categories.parent'))
                            ->helperText(__('admin.categories.parent_help'))
                            ->options(fn (?Category $record): array => self::parentOptions($record))
                            ->searchable()
                            ->placeholder('—'),
                        TextInput::make('slug')
                            ->label(__('admin.categories.slug'))
                            ->helperText(__('admin.categories.slug_help'))
                            ->required()
                            ->maxLength(150)
                            ->mutateStateForValidationUsing(fn (?string $state): string => Persian::slug($state))
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
                            ->prefix('/category/'),
                        Textarea::make('description.fa')
                            ->label(__('admin.categories.description'))
                            ->placeholder(__('admin.categories.description_placeholder'))
                            ->rows(2)
                            ->maxLength(300),
                    ]),

                Section::make(__('admin.categories.section_display'))
                    ->columns(2)
                    ->schema([
                        Select::make('icon')
                            ->label(__('admin.categories.icon'))
                            ->helperText(__('admin.categories.icon_help'))
                            ->options(self::iconOptions())
                            ->allowHtml()
                            ->searchable(),
                        FileUpload::make('image_path')
                            ->label(__('admin.categories.image'))
                            ->helperText(__('admin.categories.image_help'))
                            ->image()
                            ->disk('public')
                            ->directory('categories')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            // عکس از نو ساخته و به WebP کوچک ذخیره می‌شود
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageStore::class)
                                ->storeAsWebp($file->getRealPath(), 'categories', maxSide: 400, quality: 80)),
                        Toggle::make('is_active')
                            ->label(__('admin.categories.is_active'))
                            ->helperText(__('admin.categories.is_active_help'))
                            ->default(true),
                        Toggle::make('show_in_menu')
                            ->label(__('admin.categories.show_in_menu'))
                            ->helperText(__('admin.categories.show_in_menu_help'))
                            ->default(true),
                        TextInput::make('position')
                            ->label(__('admin.categories.position'))
                            ->helperText(__('admin.categories.position_help'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                    ]),

                Section::make(__('admin.seo.section'))
                    ->description(__('admin.seo.section_help'))
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title.fa')
                            ->label(__('admin.seo.meta_title'))
                            ->helperText(__('admin.seo.meta_title_help'))
                            ->maxLength(70),
                        Textarea::make('meta_description.fa')
                            ->label(__('admin.seo.meta_description'))
                            ->helperText(__('admin.seo.meta_description_help'))
                            ->rows(2)
                            ->maxLength(160),
                    ]),
            ]);
    }

    /**
     * دسته‌هایی که این دسته می‌تواند زیرشان برود: نه خودش، نه زیردسته‌هایش، و طوری که
     * درخت از سه سطح عمیق‌تر نشود. نام با مسیر: «موبایل › لوازم جانبی».
     *
     * @return array<int, string>
     */
    public static function parentOptions(?Category $record): array
    {
        $all = Category::query()->orderBy('depth')->orderBy('position')->orderBy('id')->get()->keyBy('id');

        $excluded = [];
        $subtreeHeight = 0;
        if ($record?->exists) {
            $descendants = $record->descendantIds();
            $excluded = [...$descendants, $record->id];
            foreach ($descendants as $id) {
                $subtreeHeight = max($subtreeHeight, (int) $all->get($id)?->depth - $record->depth);
            }
        }

        $rows = [];
        foreach ($all as $category) {
            if (in_array($category->id, $excluded, true) || $category->depth + 1 + $subtreeHeight > Category::MAX_DEPTH) {
                continue;
            }
            $chain = array_filter(array_map(fn (int $id) => $all->get($id), [...$category->ancestorIds(), $category->id]));
            // ترتیب درختی: اول دسته‌ی اصلی، بعد گروه‌هایش (به ترتیب «ترتیب»)
            $sortKey = implode('|', array_map(fn (Category $c): string => sprintf('%08d-%08d', $c->position, $c->id), $chain));
            $rows[$sortKey] = [$category->id, implode(' › ', array_map(fn (Category $c): string => $c->tr('name'), $chain))];
        }
        ksort($rows);

        $options = [];
        foreach ($rows as [$id, $label]) {
            $options[$id] = $label;
        }

        return $options;
    }

    /** @return array<string, string> آیکون با پیش‌نمایش */
    private static function iconOptions(): array
    {
        $options = [];
        foreach (Category::ICONS as $icon) {
            $svg = svg('heroicon-o-'.$icon, '', ['style' => 'width:1.25rem;height:1.25rem;display:inline-block;vertical-align:middle'])->toHtml();
            $options[$icon] = $svg.' <span dir="ltr">'.e($icon).'</span>';
        }

        return $options;
    }
}
