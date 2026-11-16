<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Support\Persian;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.pages.section_main'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.fa')
                            ->label(__('admin.pages.title'))
                            ->placeholder(__('admin.pages.title_placeholder'))
                            ->required()
                            ->maxLength(150)
                            ->autofocus(),
                        TextInput::make('slug')
                            ->label(__('admin.pages.slug'))
                            ->placeholder('about')
                            ->helperText(__('admin.pages.slug_help'))
                            ->required()
                            ->maxLength(150)
                            ->mutateStateForValidationUsing(fn (?string $state): string => Persian::slug($state))
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        RichEditor::make('body.fa')
                            ->label(__('admin.pages.body'))
                            ->helperText(__('admin.pages.body_help'))
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.pages.section_display'))
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('admin.pages.is_active'))
                            ->helperText(__('admin.pages.is_active_help'))
                            ->default(true),
                        Toggle::make('show_in_footer')
                            ->label(__('admin.pages.show_in_footer'))
                            ->helperText(__('admin.pages.show_in_footer_help'))
                            ->default(true),
                        TextInput::make('position')
                            ->label(__('admin.pages.position'))
                            ->helperText(__('admin.pages.position_help'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                    ]),

                Section::make(__('admin.seo.section'))
                    ->description(__('admin.seo.section_help'))
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title.fa')
                            ->label(__('admin.seo.meta_title'))
                            ->helperText(__('admin.seo.meta_title_help'))
                            ->maxLength(70),
                        Textarea::make('meta_description.fa')
                            ->label(__('admin.seo.meta_description'))
                            ->helperText(__('admin.seo.meta_description_help'))
                            ->rows(2)
                            ->maxLength(160),
                    ]),
            ]);
    }
}
