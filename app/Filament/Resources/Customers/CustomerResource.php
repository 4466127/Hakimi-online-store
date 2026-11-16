<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Support\Jalali;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مشتریانی که در سایت ثبت‌نام کرده‌اند. مدیر نام، فعال بودن و یادداشت را عوض می‌کند.
 * (سفارش‌های هر مشتری در مرحله‌ی ۴ اضافه می‌شود.)
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return (string) __('admin.nav.marketplace');
    }

    public static function getModelLabel(): string
    {
        return (string) __('admin.customers.single');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.customers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.customers.name'))
                            ->required()
                            ->maxLength(100),
                        Placeholder::make('phone_display')
                            ->label(__('admin.customers.phone'))
                            ->content(fn (?Customer $record): string => Phone::display($record?->phone)),
                        TextInput::make('email')
                            ->label(__('admin.customers.email'))
                            ->email()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Toggle::make('is_active')
                            ->label(__('admin.customers.is_active'))
                            ->helperText(__('admin.customers.is_active_help')),
                        Textarea::make('notes')
                            ->label(__('admin.customers.notes'))
                            ->helperText(__('admin.customers.notes_help'))
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('seller'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.customers.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('admin.customers.phone'))
                    ->formatStateUsing(fn (?string $state): string => Phone::display($state))
                    ->searchable(),
                TextColumn::make('seller.status')
                    ->label(__('admin.customers.is_seller'))
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->badge()
                    ->color(fn ($state): string => $state?->color() ?? 'gray')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('admin.customers.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.customers.registered_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(__('admin.customers.last_login_at'))
                    ->formatStateUsing(fn ($state): string => Jalali::dateTime($state))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.customers.is_active')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading(__('admin.customers.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
