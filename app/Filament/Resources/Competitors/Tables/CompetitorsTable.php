<?php

namespace App\Filament\Resources\Competitors\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompetitorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('competitors.fields.name'))
                    ->searchable(),
                TextColumn::make('region')
                    ->label(__('competitors.fields.region'))
                    ->searchable(),
                TextColumn::make('market_segments')
                    ->label(__('competitors.fields.market_segments'))
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('competitors.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('competitors.fields.updated_at'))
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
