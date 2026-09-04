<?php

namespace App\Filament\Resources\ServiceCategories\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('service_categories.fields.name'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('service_categories.fields.code'))
                    ->badge(),
                IconColumn::make('active')
                    ->label(__('service_categories.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('service_categories.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('service_categories.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
