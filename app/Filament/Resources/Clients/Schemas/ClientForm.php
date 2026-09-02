<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('clients.form.section_heading'))
                    ->description(__('clients.form.section_description'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('clients.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('region')
                            ->label(__('clients.fields.region'))
                            ->prefixIcon(Heroicon::OutlinedMapPin),
                        Toggle::make('active')
                            ->label(__('clients.fields.active'))
                            ->helperText(__('clients.fields.active_helper'))
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('clients.fields.notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
