<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomeVideos;

use App\Filament\Resources\HomeVideos\Pages\CreateHomeVideo;
use App\Filament\Resources\HomeVideos\Pages\EditHomeVideo;
use App\Filament\Resources\HomeVideos\Pages\ListHomeVideos;
use App\Models\HomeVideo;
use App\Models\Product;
use App\Services\ImageStore;
use App\Support\Num;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * ویدیوهای صفحه‌ی اصلی (۵ قوس ۱۴۰۵): ویدیوی کوتاه، عکس پیش‌نمایش، عنوان، لینک، چند جنس؛ حداکثر ۳ روشن.
 * فایل ویدیو با نام تصادفی در public/videos؛ نوع فایل از محتوای خود فایل بررسی می‌شود (نه از پسوند).
 */
class HomeVideoResource extends Resource
{
    protected static ?string $model = HomeVideo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.home_videos.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.home_videos.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.home_videos.section'))
                    ->description(__('admin.home_videos.help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.fa')
                            ->label(__('admin.home_videos.title'))
                            ->placeholder(__('admin.home_videos.title_placeholder'))
                            ->helperText(__('admin.home_videos.title_help'))
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        FileUpload::make('video_path')
                            ->label(__('admin.home_videos.video'))
                            ->helperText(__('admin.home_videos.video_help', ['mb' => Num::toFa((string) intdiv(HomeVideo::MAX_VIDEO_KB, 1024))]))
                            ->required()
                            ->disk('public')
                            ->directory('videos')
                            ->visibility('public')
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->maxSize(HomeVideo::MAX_VIDEO_KB),
                        FileUpload::make('poster_path')
                            ->label(__('admin.home_videos.poster'))
                            ->helperText(__('admin.home_videos.poster_help'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('videos')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            // عکس از نو ساخته و به WebP کوچک ذخیره می‌شود (کد پنهان و EXIF پاک)
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageStore::class)
                                ->storeAsWebp($file->getRealPath(), 'videos', maxSide: 1200, quality: 78)),
                        TextInput::make('link_url')
                            ->label(__('admin.home_videos.link'))
                            ->placeholder('/category/boot')
                            ->helperText(__('admin.banners.link_help'))
                            ->maxLength(255)
                            // فقط آدرس داخل سایت («/…») یا http(s)؛ هرگز javascript: و مانند آن
                            ->regex('#^(/(?!/)\S*|https?://\S+)$#i')
                            ->validationMessages(['regex' => __('admin.banners.link_invalid')])
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Select::make('product_ids')
                            ->label(__('admin.home_videos.products'))
                            ->helperText(__('admin.home_videos.products_help', ['max' => Num::toFa((string) HomeVideo::MAX_PRODUCTS)]))
                            ->multiple()
                            ->maxItems(HomeVideo::MAX_PRODUCTS)
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where('search_text', 'like', '%'.mb_substr($search, 0, 50).'%')
                                ->orderByDesc('id')->limit(30)->get()
                                ->mapWithKeys(fn (Product $p): array => [$p->id => $p->tr('name')])->all())
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()->whereIn('id', $values)->get()
                                ->mapWithKeys(fn (Product $p): array => [$p->id => $p->tr('name')])->all()),
                        TextInput::make('position')
                            ->label(__('admin.banners.position'))
                            ->helperText(__('admin.banners.position_help'))
                            ->integer()->minValue(0)
                            ->default(fn (): int => (int) HomeVideo::query()->max('position') + 1)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('admin.banners.is_active'))
                            ->helperText(__('admin.home_videos.is_active_help', ['max' => Num::toFa((string) HomeVideo::MAX_ACTIVE)]))
                            ->default(fn (): bool => HomeVideo::activeCountExcept(null) < HomeVideo::MAX_ACTIVE)
                            ->rules([
                                fn (?HomeVideo $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $wasActive = $record?->is_active ?? false;
                                    if ($value && ! $wasActive && HomeVideo::activeCountExcept($record?->id) >= HomeVideo::MAX_ACTIVE) {
                                        $fail(__('admin.home_videos.too_many', ['max' => Num::toFa((string) HomeVideo::MAX_ACTIVE)]));
                                    }
                                },
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.home_videos.table_help', ['max' => Num::toFa((string) HomeVideo::MAX_ACTIVE)]))
            ->columns([
                ImageColumn::make('poster_path')
                    ->label(__('admin.home_videos.poster'))
                    ->disk('public')
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label(__('admin.home_videos.title'))
                    ->state(fn (HomeVideo $record): string => $record->tr('title')),
                TextColumn::make('product_ids')
                    ->label(__('admin.home_videos.products_count'))
                    ->state(fn (HomeVideo $record): string => Num::toFa((string) count((array) ($record->product_ids ?? [])))),
                TextColumn::make('position')
                    ->label(__('admin.banners.position'))
                    ->formatStateUsing(fn ($state): string => Num::toFa((string) $state)),
                IconColumn::make('is_active')
                    ->label(__('admin.banners.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('admin.home_videos.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeVideos::route('/'),
            'create' => CreateHomeVideo::route('/create'),
            'edit' => EditHomeVideo::route('/{record}/edit'),
        ];
    }
}
