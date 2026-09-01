<?php

namespace App\Filament\Resources\ConceptBlocks\Schemas;

use App\Enums\ConceptBlockCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConceptBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('concept_blocks.form.details_heading'))
                    ->description(__('concept_blocks.form.details_description'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema([
                        Select::make('category')
                            ->label(__('concept_blocks.fields.category'))
                            ->options(ConceptBlockCategory::class)
                            ->searchable()
                            ->preload()
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->required(),
                        TextInput::make('title')
                            ->label(__('concept_blocks.fields.title'))
                            ->prefixIcon(Heroicon::OutlinedBookOpen)
                            ->required(),
                        Textarea::make('content')
                            ->label(__('concept_blocks.fields.content'))
                            ->helperText(__('concept_blocks.form.content_helper'))
                            ->rows(10)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
