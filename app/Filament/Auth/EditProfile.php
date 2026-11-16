<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Phone;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «حساب من»: نام و رمز خود کاربر. شماره‌ی تلفن را فقط صاحب فروشگاه از بخش کاربران عوض می‌کند.
 * کاربری که هنوز رمز اولیه دارد به همین صفحه فرستاده می‌شود تا رمز خودش را بگذارد.
 */
class EditProfile extends BaseEditProfile
{
    /** آیا کاربر مجبور به عوض کردن رمز به این صفحه آمده است؟ */
    public bool $mustChange = false;

    #[Unexposed]
    public function mount(): void
    {
        $user = $this->getUser();
        $this->mustChange = $user instanceof User && $user->mustChangePassword();

        parent::mount();
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent()
                    ->label(__('admin.users.name'))
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label(__('admin.users.phone'))
                    ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                    ->helperText(__('admin.profile.phone_help'))
                    ->disabled()
                    ->dehydrated(false),
                $this->getPasswordFormComponent()
                    ->label(__('admin.profile.new_password'))
                    ->helperText(__('admin.profile.password_help'))
                    ->required(fn (): bool => $this->mustChange),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
    }

    #[Unexposed]
    public function getSubheading(): string|Htmlable|null
    {
        return $this->mustChange ? __('admin.profile.must_change') : null;
    }

    /** کاربری که مجبور به تغییر رمز بود، بعد از ذخیره به داشبورد می‌رود. */
    protected function getRedirectUrl(): ?string
    {
        $user = $this->getUser();
        if ($this->mustChange && $user instanceof User && ! $user->mustChangePassword()) {
            return Filament::getUrl();
        }

        return null;
    }
}
