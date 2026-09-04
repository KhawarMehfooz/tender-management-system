<?php

namespace App\Filament\Resources\ConceptBlocks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConceptBlockInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('concept_blocks.form.details_heading'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema([
                        TextEntry::make('category')
                            ->label(__('concept_blocks.fields.category'))
                            ->badge(),
                        TextEntry::make('title')
                            ->label(__('concept_blocks.fields.title')),
                        TextEntry::make('currentVersion.version_number')
                            ->label(__('concept_blocks.fields.version_number'))
                            ->placeholder('-'),
                        TextEntry::make('currentVersion.content')
                            ->label(__('concept_blocks.fields.content'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('concept_blocks.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label(__('concept_blocks.fields.created_by'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('concept_blocks.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('concept_blocks.fields.updated_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
