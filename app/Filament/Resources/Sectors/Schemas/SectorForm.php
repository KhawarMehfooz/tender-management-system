<?php

namespace App\Filament\Resources\Sectors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SectorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('sectors.form.section_heading'))
                    ->description(__('sectors.form.section_description'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('sectors.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedBriefcase)
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('active')
                            ->label(__('sectors.fields.active'))
                            ->helperText(__('sectors.fields.active_helper'))
                            ->required(),
                    ]),
            ]);
    }
}
