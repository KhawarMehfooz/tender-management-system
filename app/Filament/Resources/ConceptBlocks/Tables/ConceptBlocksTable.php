<?php

namespace App\Filament\Resources\ConceptBlocks\Tables;

use App\Enums\ConceptBlockCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConceptBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('concept_blocks.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('concept_blocks.fields.category'))
                    ->badge(),
                TextColumn::make('currentVersion.version_number')
                    ->label(__('concept_blocks.fields.version_number'))
                    ->placeholder('-'),
                TextColumn::make('createdBy.name')
                    ->label(__('concept_blocks.fields.created_by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('concept_blocks.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('concept_blocks.fields.category'))
                    ->options(ConceptBlockCategory::class),
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
