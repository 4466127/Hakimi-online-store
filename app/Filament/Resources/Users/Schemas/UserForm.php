<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Rules\AfghanMobile;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.users.section_identity'))
                    ->description(__('admin.users.section_identity_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.users.name'))
                            ->placeholder(__('admin.users.name_placeholder'))
                            ->required()
                            ->maxLength(100)
                            ->autofocus(),
                        TextInput::make('phone')
                            ->label(__('admin.users.phone'))
                            ->placeholder(__('admin.auth.phone_placeholder'))
                            ->helperText(__('admin.users.phone_help'))
                            ->required()
                            ->maxLength(25)
                            ->rule(new AfghanMobile)
                            ->mutateStateForValidationUsing(fn (?string $state): ?string => Phone::normalize($state) ?? $state)
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['inputmode' => 'tel', 'dir' => 'ltr']),
                        TextInput::make('email')
                            ->label(__('admin.users.email'))
                            ->placeholder('name@example.com')
                            ->helperText(__('admin.optional'))
                            ->email()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),

                Section::make(__('admin.users.section_access'))
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label(__('admin.users.role'))
                            ->options(UserRole::options())
                            ->default(UserRole::Staff->value)
                            ->required()
                            ->live()
                            ->helperText(function ($state): string {
                                $role = $state instanceof UserRole ? $state : UserRole::tryFrom((string) $state);

                                return $role ? $role->description() : (string) __('admin.users.role_help');
                            })
                            // نقش آخرین صاحب فروشگاه عوض نمی‌شود، وگرنه هیچ‌کس به «کاربران» دسترسی ندارد
                            ->disabled(fn (?User $record): bool => $record !== null && UserPolicy::isLastActiveOwner($record)),
                        Toggle::make('is_active')
                            ->label(__('admin.users.is_active'))
                            ->helperText(__('admin.users.is_active_help'))
                            ->default(true)
                            ->disabled(fn (?User $record): bool => $record !== null
                                && ($record->is(auth()->user()) || UserPolicy::isLastActiveOwner($record))),
                    ]),

                Section::make(__('admin.users.section_password'))
                    ->schema([
                        TextInput::make('password')
                            ->label(fn (string $operation): string => (string) __($operation === 'create' ? 'admin.users.password' : 'admin.users.password_new'))
                            ->helperText(fn (string $operation): string => (string) __($operation === 'create' ? 'admin.users.password_help_create' : 'admin.users.password_help_edit'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->rule(Password::default())
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }
}
