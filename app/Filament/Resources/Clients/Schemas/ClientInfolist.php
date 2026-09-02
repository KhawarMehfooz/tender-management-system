<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('clients.form.section_heading'))
                    ->description(__('clients.form.section_description'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('clients.fields.name')),
                        TextEntry::make('region')
                            ->label(__('clients.fields.region'))
                            ->placeholder('—'),
                        IconEntry::make('active')
                            ->label(__('clients.fields.active'))
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label(__('clients.fields.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('clients.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('clients.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('clients.fields.updated_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
