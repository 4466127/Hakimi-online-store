<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * تنظیمات بازارگاه: کمیسیون پیش‌فرض، دوره‌ی تسویه و مزایای فروشندگان تازه.
 * (نرخ هر دسته در «دسته‌بندی اجناس»؛ نرخ ویژه‌ی هر فروشنده در صفحه‌ی همان فروشنده.)
 * فقط صاحب و مدیر. هر تغییر در تاریخچه‌ی فعالیت.
 *
 * @property-read Schema $form
 */
class ManageMarketplaceSettings extends Page
{
    /** نام خانه‌ی فورم ← کلید تنظیم */
    private const FIELDS = [
        'default_commission_rate' => 'marketplace.default_commission_rate',
        'default_settlement_cycle' => 'marketplace.default_settlement_cycle',
        'social_free_commission_days' => 'marketplace.social_free_commission_days',
        'local_producer_rate' => 'marketplace.local_producer_rate',
        'local_producer_days' => 'marketplace.local_producer_days',
        'brand_free_pickup_days' => 'marketplace.brand_free_pickup_days',
        'brand_settlement_cycle' => 'marketplace.brand_settlement_cycle',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'marketplace-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canManageSettings();
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.marketplace_settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('admin.marketplace_settings.title');
    }

    public function mount(): void
    {
        $settings = app(Settings::class);
        $state = [];
        foreach (self::FIELDS as $field => $key) {
            $state[$field] = $settings->get($key);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $cycles = (array) __('commission.cycles');
        $days = fn (string $name) => TextInput::make($name)
            ->label(__('admin.marketplace_settings.'.$name))
            ->numeric()->integer()->minValue(0)->maxValue(730)
            ->suffix(__('admin.marketplace_settings.days_suffix'))
            ->required();

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.marketplace_settings.section_commission'))
                    ->description(__('admin.marketplace_settings.section_commission_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_commission_rate')
                            ->label(__('admin.marketplace_settings.default_commission_rate'))
                            ->helperText(__('admin.marketplace_settings.default_commission_rate_help'))
                            ->numeric()->minValue(0)->maxValue(50)->step('0.01')
                            ->suffix('٪')
                            ->required(),
                        Select::make('default_settlement_cycle')
                            ->label(__('admin.marketplace_settings.default_settlement_cycle'))
                            ->options($cycles)
                            ->required(),
                    ]),

                Section::make(__('admin.marketplace_settings.section_incentives'))
                    ->description(__('admin.marketplace_settings.section_incentives_help'))
                    ->columns(2)
                    ->schema([
                        $days('social_free_commission_days'),
                        $days('brand_free_pickup_days'),
                        TextInput::make('local_producer_rate')
                            ->label(__('admin.marketplace_settings.local_producer_rate'))
                            ->numeric()->minValue(0)->maxValue(50)->step('0.01')
                            ->suffix('٪')
                            ->required(),
                        $days('local_producer_days'),
                        Select::make('brand_settlement_cycle')
                            ->label(__('admin.marketplace_settings.brand_settlement_cycle'))
                            ->options($cycles)
                            ->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('admin.save'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $values = [];
        foreach (self::FIELDS as $field => $key) {
            $value = $data[$field] ?? null;
            // عددها به شکل عدد ذخیره شود (نه متن)
            $values[$key] = is_numeric($value) ? $value + 0 : $value;
        }

        $changes = app(Settings::class)->setMany($values, auth()->id());
        if ($changes !== []) {
            app(ActivityLogger::class)->log('settings.updated', null, $changes);
        }

        Notification::make()->success()->title(__('admin.saved'))->send();
    }
}
