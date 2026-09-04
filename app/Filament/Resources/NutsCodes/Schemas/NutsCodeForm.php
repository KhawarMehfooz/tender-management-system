<?php

namespace App\Filament\Resources\NutsCodes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class NutsCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('nuts_codes.form.section_heading'))
                    ->description(__('nuts_codes.form.section_description'))
                    ->icon(Heroicon::OutlinedMap)
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('nuts_codes.fields.code'))
                            ->prefixIcon(Heroicon::OutlinedHashtag)
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('label')
                            ->label(__('nuts_codes.fields.label'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->required(),
                        TextInput::make('level')
                            ->label(__('nuts_codes.fields.level'))
                            ->prefixIcon(Heroicon::OutlinedRectangleGroup)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3)
                            ->required(),
                        Select::make('parent_id')
                            ->label(__('nuts_codes.fields.parent'))
                            ->prefixIcon(Heroicon::OutlinedMap)
                            ->relationship(
                                'parent',
                                'label',
                                modifyQueryUsing: fn ($query, $record) => $record
                                    ? $query->whereKeyNot($record->id)
                                    : $query,
                            )
                            ->searchable()
                            ->preload(),
                        Toggle::make('active')
                            ->label(__('nuts_codes.fields.active'))
                            ->helperText(__('nuts_codes.fields.active_helper'))
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
