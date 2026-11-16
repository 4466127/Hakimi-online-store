<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\User;
use App\Rules\AfghanMobile;
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
 * «حساب من»: نام، شماره‌ی تلفن (همان شماره‌ای که با آن وارد می‌شود) و رمز خود کاربر.
 * عوض کردن شماره یا رمز، وارد کردن رمز فعلی را می‌خواهد (جلوی دزدیدن حساب با یک نشست باز).
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
                    ->placeholder(__('admin.auth.phone_placeholder'))
                    ->helperText(__('admin.profile.phone_help'))
                    ->required()
                    ->maxLength(25)
                    ->rule(new AfghanMobile)
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => Phone::normalize($state) ?? $state)
                    ->unique(table: 'users', column: 'phone', ignorable: fn (): ?User => $this->getUser() instanceof User ? $this->getUser() : null)
                    ->dehydrateStateUsing(fn (?string $state): ?string => Phone::normalize($state) ?? $state)
                    ->extraInputAttributes(['inputmode' => 'tel', 'dir' => 'ltr'])
                    ->live(onBlur: true),
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
        // رمز فعلی وقتی لازم است که رمز یا شماره عوض شود
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')) || $this->phoneChanged($get('phone')));
    }

    /** شماره‌ی نوشته‌شده با شماره‌ی فعلی کاربر فرق دارد؟ (ارقام فارسی و +93 یکی حساب می‌شود) */
    private function phoneChanged(?string $typed): bool
    {
        $user = $this->getUser();
        $normalized = Phone::normalize((string) $typed);

        return $user instanceof User && $normalized !== null && $normalized !== $user->phone;
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
