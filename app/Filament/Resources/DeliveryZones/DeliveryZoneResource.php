<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones;

use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Models\DeliveryZone;
use App\Models\Province;
use App\Support\Num;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * کرایه‌ی تحویل: کرایه‌ی هر «روز تحویل» (زودترین گران‌تر، دیرتر ارزان‌تر)، اضافه‌ی «وزن بار»،
 * ساعت قطع سفارش و روزهای بی تحویل. همه‌ی عددها از همین‌جا عوض می‌شود.
 */
class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 16;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.system');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.delivery.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.delivery.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('admin.delivery.section_zone'))
                ->columns(2)
                ->schema([
                    TextInput::make('name.fa')
                        ->label(__('admin.delivery.name'))
                        ->placeholder(__('admin.delivery.name_placeholder'))
                        ->required()
                        ->maxLength(100),
                    Select::make('province_id')
                        ->label(__('admin.delivery.province'))
                        ->helperText(__('admin.delivery.province_help'))
                        ->options(fn (): array => Province::query()->where('is_active', true)->orderBy('position')->get()
                            ->mapWithKeys(fn (Province $p) => [$p->id => $p->tr('name')])->all())
                        ->placeholder(__('admin.delivery.province_any'))
                        ->searchable()
                        ->unique(ignoreRecord: true),
                    Toggle::make('is_active')
                        ->label(__('admin.delivery.is_active'))
                        ->helperText(__('admin.delivery.is_active_help'))
                        ->default(true)
                        ->inline(false),
                ]),

            Section::make(__('admin.delivery.section_slots'))
                ->description(__('admin.delivery.slots_help'))
                ->schema([
                    Repeater::make('slots')
                        ->relationship('slots')
                        ->hiddenLabel()
                        ->orderColumn('day_rank')
                        ->reorderableWithButtons()
                        ->minItems(1)
                        ->itemLabel(fn (string $uuid, Repeater $component): string => self::rowLabel('admin.delivery.slot_item', $uuid, $component))
                        ->addActionLabel(__('admin.delivery.slot_add'))
                        ->columns(2)
                        ->schema([
                            TextInput::make('fee')
                                ->label(__('admin.delivery.slot_fee'))
                                ->placeholder('300')
                                ->required()
                                ->inputMode('decimal')
                                ->maxLength(12)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->formatStateUsing(fn ($state): ?string => self::plain($state))
                                ->dehydrateStateUsing(fn ($state): string => Num::parse((string) $state) ?? '0')
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $n = Num::parse((string) $value);
                                    if ($n === null || bccomp($n, '0', 2) < 0) {
                                        $fail((string) __('admin.delivery.fee_invalid'));
                                    }
                                }),
                            Toggle::make('is_active')
                                ->label(__('admin.delivery.slot_active'))
                                ->default(true)
                                ->inline(false),
                        ]),
                ]),

            Section::make(__('admin.delivery.section_weight'))
                ->description(__('admin.delivery.weight_help'))
                ->schema([
                    Repeater::make('weightTiers')
                        ->relationship('weightTiers')
                        ->hiddenLabel()
                        ->orderColumn('position')
                        ->reorderableWithButtons()
                        ->itemLabel(fn (string $uuid, Repeater $component): string => self::rowLabel('admin.delivery.tier_item', $uuid, $component))
                        ->addActionLabel(__('admin.delivery.tier_add'))
                        ->columns(2)
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (($message = self::tierProblem((array) $value)) !== null) {
                                $fail($message);
                            }
                        }])
                        ->schema([
                            TextInput::make('up_to_grams')
                                ->label(__('admin.delivery.tier_up_to'))
                                ->helperText(__('admin.delivery.tier_up_to_help'))
                                ->placeholder(__('admin.delivery.tier_up_to_open'))
                                ->inputMode('decimal')
                                ->maxLength(10)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                // در فورم کیلو، در دیتابیس گرام
                                ->formatStateUsing(fn ($state): ?string => $state === null || $state === '' ? null : self::plain(bcdiv((string) $state, '1000', 3)))
                                ->dehydrateStateUsing(fn ($state): ?int => ($kg = Num::parse((string) $state)) === null ? null : (int) round((float) $kg * 1000)),
                            TextInput::make('extra_fee')
                                ->label(__('admin.delivery.tier_fee'))
                                ->placeholder('0')
                                ->required()
                                ->inputMode('decimal')
                                ->maxLength(12)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->formatStateUsing(fn ($state): ?string => self::plain($state))
                                ->dehydrateStateUsing(fn ($state): string => Num::parse((string) $state) ?? '0'),
                        ]),
                ]),

            Section::make(__('admin.delivery.section_schedule'))
                ->columns(2)
                ->schema([
                    Select::make('cutoff_hour')
                        ->label(__('admin.delivery.cutoff_hour'))
                        ->helperText(__('admin.delivery.cutoff_hour_help'))
                        ->options(collect(range(0, 23))->mapWithKeys(fn (int $h) => [$h => Num::toFa(sprintf('%02d:00', $h))])->all())
                        ->default(16)
                        ->required(),
                    CheckboxList::make('days_off')
                        ->label(__('admin.delivery.days_off'))
                        ->helperText(__('admin.delivery.days_off_help'))
                        ->options(self::weekdayOptions())
                        ->columns(4),
                    TextInput::make('default_item_weight_grams')
                        ->label(__('admin.delivery.default_weight'))
                        ->helperText(__('admin.delivery.default_weight_help'))
                        ->numeric()->integer()->minValue(0)->maxValue(100000)
                        ->default(500)
                        ->required(),
                    TextInput::make('max_weight_grams')
                        ->label(__('admin.delivery.max_weight'))
                        ->helperText(__('admin.delivery.max_weight_help'))
                        ->numeric()->integer()->minValue(1)->maxValue(10000000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('admin.delivery.help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['province', 'slots', 'weightTiers']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.delivery.name'))
                    ->state(fn (DeliveryZone $record): string => $record->tr('name'))
                    ->weight('bold'),
                TextColumn::make('province')
                    ->label(__('admin.delivery.province'))
                    ->state(fn (DeliveryZone $record): string => $record->province?->tr('name') ?? (string) __('admin.delivery.province_any')),
                TextColumn::make('slots_summary')
                    ->label(__('admin.delivery.slots_summary'))
                    ->state(fn (DeliveryZone $record): string => $record->slots->where('is_active', true)
                        ->map(fn ($s) => Num::toFa((string) self::plain($s->fee)))->implode(' ← ').' '.__('store.currency_fallback')),
                TextColumn::make('weight_summary')
                    ->label(__('admin.delivery.weight_summary'))
                    ->state(fn (DeliveryZone $record): string => (string) __('admin.delivery.weight_summary_text', ['count' => Num::toFa((string) $record->weightTiers->count())])),
                ToggleColumn::make('is_active')->label(__('admin.delivery.is_active')),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('position')
            ->emptyStateHeading(__('admin.delivery.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryZones::route('/'),
            'create' => CreateDeliveryZone::route('/create'),
            'edit' => EditDeliveryZone::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> روز هفته‌ی ISO ← نام (هفته از شنبه شروع می‌شود). */
    public static function weekdayOptions(): array
    {
        $names = (array) __('date.weekdays');   // ۰ = یکشنبه
        $options = [];
        foreach ([6, 7, 1, 2, 3, 4, 5] as $iso) {
            $options[$iso] = (string) ($names[$iso % 7] ?? $iso);
        }

        return $options;
    }

    /**
     * مشکل پله‌های وزنی یا null. پله‌ی بی عدد («سنگین‌تر از همه») هر جا باشد کار می‌کند (محاسبه‌گر آن را
     * همیشه بعد از پله‌های عددی حساب می‌کند)، ولی فقط یکی؛ وزن تکراری و صفر هم نه. پیام شماره‌ی پله را می‌گوید.
     *
     * @param  array<int|string, array<string, mixed>>  $tiers
     */
    public static function tierProblem(array $tiers): ?string
    {
        $seen = [];
        $open = [];
        foreach (array_values($tiers) as $i => $row) {
            $n = $i + 1;
            $kg = Num::parse((string) ($row['up_to_grams'] ?? ''));
            if ($kg === null) {
                $open[] = $n;

                continue;
            }
            if (bccomp($kg, '0', 3) <= 0) {
                return (string) __('admin.delivery.tier_weight_invalid', ['n' => Num::toFa((string) $n)]);
            }
            if (isset($seen[$kg])) {
                return (string) __('admin.delivery.tier_duplicate', ['a' => Num::toFa((string) $seen[$kg]), 'b' => Num::toFa((string) $n)]);
            }
            $seen[$kg] = $n;
        }

        if (count($open) > 1) {
            return (string) __('admin.delivery.tier_open_only_one', ['rows' => implode('، ', array_map(fn (int $n) => Num::toFa((string) $n), $open))]);
        }

        return null;
    }

    /** عنوان هر ردیف تکرارشونده: «روز ۱»، «روز ۲»، … (به ترتیب فعلی ردیف‌ها). */
    private static function rowLabel(string $key, string $uuid, Repeater $component): string
    {
        $position = array_search($uuid, array_keys((array) $component->getRawState()), true);

        return (string) __($key, ['n' => Num::toFa((string) (($position === false ? 0 : $position) + 1))]);
    }

    /** «300.00» ← «300»، «0.50» ← «0.5»؛ عدد کامل بی نقطه دست نمی‌خورد. */
    public static function plain(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = (string) $value;

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
