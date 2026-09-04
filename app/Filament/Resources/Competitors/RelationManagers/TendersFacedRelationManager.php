<?php

namespace App\Filament\Resources\Competitors\RelationManagers;

use App\Enums\CompetitorOutcome;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only mirror of CompetitorsRelationManager: the tenders this competitor was seen on,
 * entered from the Tender side. No create/edit/delete here — the pivot row is only ever
 * managed from TenderResource's own Competitors tab.
 */
class TendersFacedRelationManager extends RelationManager
{
    protected static string $relationship = 'tenderCompetitors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitors.tenders_faced_tab');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tender.title')
            ->columns([
                TextColumn::make('tender.title')
                    ->label(__('competitors.fields.tender'))
                    ->searchable(),
                TextColumn::make('outcome')
                    ->label(__('tender_competitors.fields.outcome'))
                    ->badge(),
                TextColumn::make('known_price')
                    ->label(__('tender_competitors.fields.known_price'))
                    ->money('EUR')
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label(__('tender_competitors.fields.notes'))
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label(__('tender_competitors.fields.outcome'))
                    ->options(CompetitorOutcome::class),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
