<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('service_categories.form.section_heading'))
                    ->description(__('service_categories.form.section_description'))
                    ->icon(Heroicon::OutlinedTag)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('service_categories.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->required()
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('service_categories.fields.description'))
                            ->rows(3),
                        Toggle::make('active')
                            ->label(__('service_categories.fields.active'))
                            ->helperText(__('service_categories.fields.active_helper'))
                            ->required(),
                    ]),
            ]);
    }
}
