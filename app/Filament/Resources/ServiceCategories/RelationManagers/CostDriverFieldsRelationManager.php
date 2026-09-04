<?php

namespace App\Filament\Resources\ServiceCategories\RelationManagers;

use App\Enums\CostDriverFieldType;
use App\Models\ServiceCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CostDriverFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'costDriverFields';

    public function form(Schema $schema): Schema
    {
        /** @var ServiceCategory $serviceCategory */
        $serviceCategory = $this->getOwnerRecord();

        return $schema
            ->components([
                TextInput::make('field_key')
                    ->label(__('service_category_cost_driver_fields.fields.field_key'))
                    ->helperText(__('service_category_cost_driver_fields.fields.field_key_helper'))
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('service_category_id', $serviceCategory->id),
                        ignoreRecord: true,
                    ),
                TextInput::make('label')
                    ->label(__('service_category_cost_driver_fields.fields.label'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('service_category_cost_driver_fields.fields.type'))
                    ->options(CostDriverFieldType::class)
                    ->required(),
                TextInput::make('unit')
                    ->label(__('service_category_cost_driver_fields.fields.unit'))
                    ->maxLength(255),
                Toggle::make('required')
                    ->label(__('service_category_cost_driver_fields.fields.required'))
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_key')
            ->columns([
                TextColumn::make('field_key')
                    ->label(__('service_category_cost_driver_fields.fields.field_key'))
                    ->searchable(),
                TextColumn::make('label')
                    ->label(__('service_category_cost_driver_fields.fields.label')),
                TextColumn::make('type')
                    ->label(__('service_category_cost_driver_fields.fields.type'))
                    ->badge(),
                TextColumn::make('unit')
                    ->label(__('service_category_cost_driver_fields.fields.unit'))
                    ->placeholder('—'),
                IconColumn::make('required')
                    ->label(__('service_category_cost_driver_fields.fields.required'))
                    ->boolean(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
