<?php

declare(strict_types=1);

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\EditBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Models\Banner;
use App\Services\ImageStore;
use App\Support\Num;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
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
 * بنرهای صفحه‌ی اصلی (مرحله‌ی ۹-۴): عکس، لینک، ترتیب، روشن/خاموش؛ حداکثر ۵ بنر روشن.
 * در سایت بی چرخش خودکار نشان داده می‌شوند؛ مشتری خودش ورق می‌زند.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.banners.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.banners.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.banners.section'))
                    ->description(__('admin.banners.help'))
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(__('admin.banners.image'))
                            ->helperText(__('admin.banners.image_help'))
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory('banners')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            // عکس از نو ساخته و به WebP کوچک (حداکثر ۱۶۰۰ پیکسل) ذخیره می‌شود
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageStore::class)
                                ->storeAsWebp($file->getRealPath(), 'banners', maxSide: 1600, quality: 78))
                            ->columnSpanFull(),
                        TextInput::make('title.fa')
                            ->label(__('admin.banners.title'))
                            ->placeholder(__('admin.banners.title_placeholder'))
                            ->helperText(__('admin.banners.title_help'))
                            ->maxLength(150),
                        TextInput::make('link_url')
                            ->label(__('admin.banners.link'))
                            ->placeholder('/category/boot')
                            ->helperText(__('admin.banners.link_help'))
                            ->maxLength(255)
                            // فقط آدرس داخل سایت («/…») یا http(s)؛ هرگز javascript: و مانند آن
                            ->regex('#^(/(?!/)\S*|https?://\S+)$#i')
                            ->validationMessages(['regex' => __('admin.banners.link_invalid')])
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('position')
                            ->label(__('admin.banners.position'))
                            ->helperText(__('admin.banners.position_help'))
                            ->integer()->minValue(0)
                            ->default(fn (): int => (int) Banner::query()->max('position') + 1)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('admin.banners.is_active'))
                            ->helperText(__('admin.banners.is_active_help', ['max' => Num::toFa((string) Banner::MAX_ACTIVE)]))
                            ->default(fn (): bool => Banner::activeCountExcept(null) < Banner::MAX_ACTIVE)
                            ->rules([
                                fn (?Banner $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $wasActive = $record?->is_active ?? false;
                                    if ($value && ! $wasActive && Banner::activeCountExcept($record?->id) >= Banner::MAX_ACTIVE) {
                                        $fail(__('admin.banners.too_many', ['max' => Num::toFa((string) Banner::MAX_ACTIVE)]));
                                    }
                                },
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.banners.table_help', ['max' => Num::toFa((string) Banner::MAX_ACTIVE)]))
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('admin.banners.image'))
                    ->disk('public')
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label(__('admin.banners.title'))
                    ->state(fn (Banner $record): string => $record->tr('title'))
                    ->placeholder('—'),
                TextColumn::make('link_url')
                    ->label(__('admin.banners.link'))
                    ->placeholder('—')
                    ->limit(40),
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
            ->emptyStateHeading(__('admin.banners.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
