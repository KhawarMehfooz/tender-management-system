<?php

namespace App\Filament\Resources\NutsCodes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NutsCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('nuts_codes.fields.code'))
                    ->searchable(),
                TextColumn::make('label')
                    ->label(__('nuts_codes.fields.label'))
                    ->searchable(),
                TextColumn::make('level')
                    ->label(__('nuts_codes.fields.level'))
                    ->sortable(),
                TextColumn::make('parent.label')
                    ->label(__('nuts_codes.fields.parent'))
                    ->placeholder('-'),
                IconColumn::make('active')
                    ->label(__('nuts_codes.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('nuts_codes.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('nuts_codes.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
