<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use App\Enums\CalculationModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('service_categories.form.section_heading'))
                    ->description(__('service_categories.form.section_description'))
                    ->icon(Heroicon::OutlinedTag)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('service_categories.fields.name'))
                            ->prefixIcon(Heroicon::OutlinedTag)
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('code')
                            ->label(__('service_categories.fields.code'))
                            ->helperText(__('service_categories.fields.code_helper'))
                            ->maxLength(4)
                            ->unique(ignoreRecord: true)
                            ->formatStateUsing(fn (?string $state): ?string => $state !== null ? strtoupper($state) : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? strtoupper($state) : null),
                        Textarea::make('description')
                            ->label(__('service_categories.fields.description'))
                            ->rows(3),
                        Select::make('calculation_model')
                            ->label(__('service_categories.fields.calculation_model'))
                            ->helperText(__('service_categories.fields.calculation_model_helper'))
                            ->options(CalculationModel::class),
                        Toggle::make('active')
                            ->label(__('service_categories.fields.active'))
                            ->helperText(__('service_categories.fields.active_helper'))
                            ->required(),
                    ]),
            ]);
    }
}
