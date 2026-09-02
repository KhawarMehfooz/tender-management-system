<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\CompetitorOutcome;
use App\Enums\Right;
use App\Models\Competitor;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Links a Competitor to a specific Tender they were seen on/competed against — the source
 * data for task 4's derived analyses (encounter counts, win/loss-against-us aggregates).
 * Gated end to end behind Right::SEE_COMPETITOR_DATA, same shape as
 * PriceEntriesRelationManager on CompetitorResource. Mirrored read-only on CompetitorResource
 * itself via TendersFacedRelationManager.
 */
class CompetitorsRelationManager extends RelationManager
{
    protected static string $relationship = 'tenderCompetitors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tender_competitors.tab_label');
    }

    private function canManage(): bool
    {
        return auth()->user()?->can(Right::SEE_COMPETITOR_DATA->value) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('competitor_id')
                ->label(__('tender_competitors.fields.competitor_id'))
                ->relationship('competitor', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('outcome')
                ->label(__('tender_competitors.fields.outcome'))
                ->options(CompetitorOutcome::class)
                ->required()
                ->default(CompetitorOutcome::UNKNOWN),
            TextInput::make('known_price')
                ->label(__('tender_competitors.fields.known_price'))
                ->prefixIcon(Heroicon::OutlinedBanknotes)
                ->numeric(),
            Textarea::make('notes')
                ->label(__('tender_competitors.fields.notes'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('competitor.name')
            ->columns([
                TextColumn::make('competitor.name')
                    ->label(__('tender_competitors.fields.competitor_id'))
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
                SelectFilter::make('competitor_id')
                    ->label(__('tender_competitors.fields.competitor_id'))
                    ->relationship('competitor', 'name'),
                SelectFilter::make('outcome')
                    ->label(__('tender_competitors.fields.outcome'))
                    ->options(CompetitorOutcome::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403)),
                    DeleteAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403)),
                ]),
            ]);
    }
}
