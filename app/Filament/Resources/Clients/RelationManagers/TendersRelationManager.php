<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only client history: every tender ever linked to this client (any status, including
 * lost, per idea.md's client-history spec), showing the outcome/winner from TenderResult and
 * the competitors seen on that tender via tender_competitors. No create/edit/delete — a
 * tender's client_id is only ever set from TenderResource's own form, never from here.
 */
class TendersRelationManager extends RelationManager
{
    protected static string $relationship = 'tenders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('clients.tenders_tab');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['result', 'tenderCompetitors.competitor']))
            ->columns([
                TextColumn::make('internal_id')
                    ->label(__('clients.tenders.fields.internal_id'))
                    ->searchable(),
                TextColumn::make('title')
                    ->label(__('clients.tenders.fields.title'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('clients.tenders.fields.status'))
                    ->badge(),
                TextColumn::make('result.winner')
                    ->label(__('clients.tenders.fields.winner'))
                    ->placeholder('—'),
                TextColumn::make('competitors')
                    ->label(__('clients.tenders.fields.competitors'))
                    ->state(fn (Tender $record): string => $record->tenderCompetitors
                        ->pluck('competitor.name')
                        ->filter()
                        ->join(', '))
                    ->placeholder('—'),
                TextColumn::make('contract_start_date')
                    ->label(__('clients.tenders.fields.contract_start_date'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('contract_end_date')
                    ->label(__('clients.tenders.fields.contract_end_date'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('clients.tenders.fields.status'))
                    ->options(TenderStatus::class),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
