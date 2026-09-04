<?php

namespace App\Filament\Resources\CpvCodes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CpvCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('cpv_codes.fields.code'))
                    ->searchable(),
                TextColumn::make('label')
                    ->label(__('cpv_codes.fields.label'))
                    ->searchable(),
                IconColumn::make('active')
                    ->label(__('cpv_codes.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('cpv_codes.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('cpv_codes.fields.updated_at'))
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
