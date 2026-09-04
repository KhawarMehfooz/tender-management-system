<?php

namespace App\Filament\Resources\References\Tables;

use App\Models\Reference;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client')
                    ->label(__('references.fields.client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serviceCategory.name')
                    ->label(__('references.fields.service_category'))
                    ->placeholder('-'),
                TextColumn::make('sector.name')
                    ->label(__('references.fields.sector'))
                    ->placeholder('-'),
                TextColumn::make('location')
                    ->label(__('references.fields.location'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contract_volume')
                    ->label(__('references.fields.contract_volume'))
                    ->formatStateUsing(fn (Reference $record): string => $record->contract_volume_unknown
                        ? __('references.fields.contract_volume_unknown')
                        : ($record->contract_volume !== null
                            ? number_format((float) $record->contract_volume, 2).' €'
                            : '-'))
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label(__('references.fields.period_end'))
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('references.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
