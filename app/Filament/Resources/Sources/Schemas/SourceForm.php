<?php

namespace App\Filament\Resources\Sources\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sources.form.section_heading'))
                    ->description(__('sources.form.section_description'))
                    ->icon(Heroicon::OutlinedRss)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('sources.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedRss)
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('active')
                            ->label(__('sources.fields.active'))
                            ->helperText(__('sources.fields.active_helper'))
                            ->required(),
                    ]),
            ]);
    }
}
