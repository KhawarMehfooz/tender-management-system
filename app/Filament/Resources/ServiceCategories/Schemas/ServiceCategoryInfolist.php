<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('service_categories.form.section_heading'))
                    ->description(__('service_categories.form.section_description'))
                    ->icon(Heroicon::OutlinedTag)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('service_categories.fields.name')),
                        TextEntry::make('code')
                            ->label(__('service_categories.fields.code'))
                            ->placeholder('-'),
                        IconEntry::make('active')
                            ->label(__('service_categories.fields.active'))
                            ->boolean(),
                        TextEntry::make('calculation_model')
                            ->label(__('service_categories.fields.calculation_model'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label(__('service_categories.fields.description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('service_categories.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('service_categories.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('service_categories.fields.updated_at'))
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
