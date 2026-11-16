<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Num;
use App\Support\Phone;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * ورود به پنل مدیریت با شماره‌ی تلفن و رمز (نه ایمیل).
 * بعد از چند رمز اشتباه، حساب برای چند دقیقه قفل می‌شود (شمارش در AppServiceProvider).
 */
class Login extends BaseLogin
{
    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        $phone = Phone::normalize($this->data['phone'] ?? null);
        $user = $phone ? User::query()->where('phone', $phone)->first() : null;

        if ($user?->isLocked()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

            throw ValidationException::withMessages([
                'data.phone' => __('admin.auth.locked', ['minutes' => Num::toFa($minutes)]),
            ]);
        }

        return parent::authenticate();
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('admin.auth.phone'))
            ->placeholder(__('admin.auth.phone_placeholder'))
            ->helperText(__('admin.auth.phone_help'))
            ->required()
            ->maxLength(25)
            ->autocomplete('tel')
            ->autofocus()
            ->extraInputAttributes(['inputmode' => 'tel', 'dir' => 'ltr']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'phone' => Phone::normalize($data['phone'] ?? null) ?? (string) ($data['phone'] ?? ''),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('admin.auth.failed'),
        ]);
    }
}
