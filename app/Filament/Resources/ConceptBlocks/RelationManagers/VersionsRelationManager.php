<?php

namespace App\Filament\Resources\ConceptBlocks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only browsable version history — no create/edit/delete actions anywhere, since
 * ConceptBlockVersion rows are immutable by construction (see EditConceptBlock, which is the
 * only place new versions get created).
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                TextColumn::make('version_number')
                    ->label(__('concept_blocks.fields.version_number'))
                    ->icon(Heroicon::OutlinedClock)
                    ->sortable(),
                TextColumn::make('content')
                    ->label(__('concept_blocks.fields.content'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('createdBy.name')
                    ->label(__('concept_blocks.fields.created_by'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('concept_blocks.fields.created_at'))
                    ->dateTime(),
            ])
            ->defaultSort('version_number', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
