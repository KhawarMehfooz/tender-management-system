<?php

namespace App\Filament\Resources\Competitors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompetitorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('competitors.form.section_heading'))
                    ->description(__('competitors.form.section_description'))
                    ->icon(Heroicon::OutlinedFlag)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('competitors.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedFlag)
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('region')
                            ->label(__('competitors.fields.region'))
                            ->prefixIcon(Heroicon::OutlinedMapPin),
                        TextInput::make('service_areas')
                            ->label(__('competitors.fields.service_areas'))
                            ->prefixIcon(Heroicon::OutlinedBriefcase)
                            ->columnSpanFull(),
                        TextInput::make('known_clients')
                            ->label(__('competitors.fields.known_clients'))
                            ->prefixIcon(Heroicon::OutlinedUserGroup)
                            ->columnSpanFull(),
                        Textarea::make('strengths')
                            ->label(__('competitors.fields.strengths')),
                        Textarea::make('weaknesses')
                            ->label(__('competitors.fields.weaknesses')),
                        TextInput::make('market_segments')
                            ->label(__('competitors.fields.market_segments'))
                            ->prefixIcon(Heroicon::OutlinedChartBar)
                            ->columnSpanFull(),
                        Textarea::make('internal_notes')
                            ->label(__('competitors.fields.internal_notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
