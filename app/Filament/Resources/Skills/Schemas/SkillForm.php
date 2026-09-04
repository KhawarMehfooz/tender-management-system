<?php

namespace App\Filament\Resources\Skills\Schemas;

use App\Enums\SkillCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('skills.form.section_heading'))
                    ->description(__('skills.form.section_description'))
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('skills.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedAcademicCap)
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('category')
                            ->label(__('skills.fields.category'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->options(SkillCategory::class),
                    ]),
            ]);
    }
}
