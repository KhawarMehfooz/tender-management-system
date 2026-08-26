<?php

namespace App\Filament\Resources\ProcurementProcedures\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProcurementProcedureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('procurement_procedures.form.section_heading'))
                    ->description(__('procurement_procedures.form.section_description'))
                    ->icon(Heroicon::OutlinedScale)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('procurement_procedures.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedScale)
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('active')
                            ->label(__('procurement_procedures.fields.active'))
                            ->helperText(__('procurement_procedures.fields.active_helper'))
                            ->required(),
                    ]),
            ]);
    }
}
