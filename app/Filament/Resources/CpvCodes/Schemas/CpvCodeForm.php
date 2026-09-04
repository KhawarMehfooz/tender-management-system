<?php

namespace App\Filament\Resources\CpvCodes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CpvCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('cpv_codes.form.section_heading'))
                    ->description(__('cpv_codes.form.section_description'))
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('cpv_codes.fields.code'))
                            ->prefixIcon(Heroicon::OutlinedHashtag)
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('label')
                            ->label(__('cpv_codes.fields.label'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->required(),
                        Toggle::make('active')
                            ->label(__('cpv_codes.fields.active'))
                            ->helperText(__('cpv_codes.fields.active_helper'))
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
