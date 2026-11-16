<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Rules\AfghanMobile;
use App\Services\ActivityLogger;
use App\Services\ImageStore;
use App\Services\Settings;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * تنظیمات فروشگاه: نام، شعار، تلفن، واتساپ، ایمیل، آدرس، لوگو و باز/بسته بودن فروشگاه.
 * فقط صاحب و مدیر. هر تغییر در تاریخچه‌ی فعالیت نوشته می‌شود.
 *
 * @property-read Schema $form
 */
class ManageStoreSettings extends Page
{
    /** نام خانه‌ی فورم ← کلید تنظیم در جدول settings */
    private const FIELDS = [
        'name' => 'store.name',
        'tagline' => 'store.tagline',
        'phone' => 'store.phone',
        'whatsapp' => 'store.whatsapp',
        'email' => 'store.email',
        'address' => 'store.address',
        'logo' => 'store.logo',
        'is_open' => 'store.is_open',
        'pickup_enabled' => 'delivery.pickup_enabled',
        'pickup_note' => 'delivery.pickup_note',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role->canManageSettings();
    }

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.system');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('admin.settings.title');
    }

    public function mount(): void
    {
        $settings = app(Settings::class);
        $state = [];
        foreach (self::FIELDS as $field => $key) {
            $state[$field] = $settings->get($key);
        }
        $state['is_open'] = (bool) $state['is_open'];
        $state['pickup_enabled'] = (bool) $state['pickup_enabled'];

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.settings.section_identity'))
                    ->description(__('admin.settings.section_identity_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.settings.name'))
                            ->placeholder(__('admin.settings.name_placeholder'))
                            ->required()
                            ->maxLength(80),
                        TextInput::make('tagline')
                            ->label(__('admin.settings.tagline'))
                            ->placeholder(__('admin.settings.tagline_placeholder'))
                            ->helperText(__('admin.optional'))
                            ->maxLength(120),
                        FileUpload::make('logo')
                            ->label(__('admin.settings.logo'))
                            ->helperText(__('admin.settings.logo_help'))
                            ->image()
                            ->disk('public')
                            ->directory('store')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            // عکس از نو ساخته و به WebP ذخیره می‌شود (امنیت + سبک بودن)
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ImageStore::class)
                                ->storeAsWebp($file->getRealPath(), 'store', maxSide: 600))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.settings.section_contact'))
                    ->description(__('admin.settings.section_contact_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('admin.settings.phone'))
                            ->placeholder(__('admin.auth.phone_placeholder'))
                            ->helperText(__('admin.settings.phone_help'))
                            ->rule(new AfghanMobile)
                            ->maxLength(25)
                            ->extraInputAttributes(['inputmode' => 'tel', 'dir' => 'ltr']),
                        TextInput::make('whatsapp')
                            ->label(__('admin.settings.whatsapp'))
                            ->placeholder(__('admin.auth.phone_placeholder'))
                            ->helperText(__('admin.settings.whatsapp_help'))
                            ->rule(new AfghanMobile)
                            ->maxLength(25)
                            ->extraInputAttributes(['inputmode' => 'tel', 'dir' => 'ltr']),
                        TextInput::make('email')
                            ->label(__('admin.settings.email'))
                            ->placeholder('info@example.com')
                            ->helperText(__('admin.optional'))
                            ->email()
                            ->maxLength(150)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('address')
                            ->label(__('admin.settings.address'))
                            ->placeholder(__('admin.settings.address_placeholder'))
                            ->helperText(__('admin.settings.address_help'))
                            ->rows(2)
                            ->maxLength(300),
                    ]),

                Section::make(__('admin.settings.section_pickup'))
                    ->description(__('admin.settings.section_pickup_help'))
                    ->schema([
                        Toggle::make('pickup_enabled')
                            ->label(__('admin.settings.pickup_enabled'))
                            ->helperText(__('admin.settings.pickup_enabled_help')),
                        Textarea::make('pickup_note')
                            ->label(__('admin.settings.pickup_note'))
                            ->placeholder(__('admin.settings.pickup_note_placeholder'))
                            ->helperText(__('admin.settings.pickup_note_help'))
                            ->rows(2)
                            ->maxLength(300),
                    ]),

                Section::make(__('admin.settings.section_status'))
                    ->schema([
                        Toggle::make('is_open')
                            ->label(__('admin.settings.is_open'))
                            ->helperText(__('admin.settings.is_open_help')),
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
        $settings = app(Settings::class);
        $oldLogo = $settings->get('store.logo');

        $values = [];
        foreach (self::FIELDS as $field => $key) {
            $values[$key] = $data[$field] ?? null;
        }
        // شماره‌ها به شکل استاندارد 07XXXXXXXX ذخیره می‌شود
        foreach (['store.phone', 'store.whatsapp'] as $key) {
            $values[$key] = Phone::normalize($values[$key]) ?? null;
        }
        $values['store.is_open'] = (bool) $values['store.is_open'];
        $values['delivery.pickup_enabled'] = (bool) $values['delivery.pickup_enabled'];
        $values['delivery.pickup_note'] = ($note = trim((string) $values['delivery.pickup_note'])) === '' ? null : $note;

        $changes = $settings->setMany($values, auth()->id());

        if ($changes !== []) {
            app(ActivityLogger::class)->log('settings.updated', null, $changes);
        }
        // لوگوی قبلی که دیگر استفاده نمی‌شود پاک شود
        if (isset($changes['store.logo']) && is_string($oldLogo)) {
            app(ImageStore::class)->delete($oldLogo);
        }

        Notification::make()
            ->success()
            ->title(__('admin.saved'))
            ->send();
    }
}
