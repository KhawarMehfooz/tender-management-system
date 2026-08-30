<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\BidDecision;
use App\Enums\Right;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\TenderParticipationScore;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;

/**
 * The decision history is append-only (idea.md's "edits create a new logged entry, not a
 * silent overwrite") — no edit/delete row actions. Both header actions are gated behind the
 * new Right::MAKE_BID_DECISION, independent of role or team membership, unlike
 * DocumentsRelationManager/CalculationsRelationManager's team-linkage checks. The read-only
 * score summary above the table is visible to anyone with tender access, same as
 * CalculationsRelationManager's approval-chain section — only the two record-mutating actions
 * are gated.
 */
class BidDecisionRelationManager extends RelationManager
{
    protected static string $relationship = 'bidDecisions';

    private function canMakeBidDecision(): bool
    {
        return auth()->user()?->can(Right::MAKE_BID_DECISION->value) ?? false;
    }

    /**
     * The tender's participation score row, or an unsaved instance carrying the tender
     * relation so contractValueRating()/marginRating()/score() still resolve correctly for a
     * tender that has no score row yet (all-null manual ratings, so score() is null).
     */
    private function currentOrNewScore(): TenderParticipationScore
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        $score = $tender->participationScore()->first();

        if ($score !== null) {
            return $score;
        }

        $score = new TenderParticipationScore(['tender_id' => $tender->id]);
        $score->setRelation('tender', $tender);

        return $score;
    }

    /**
     * @return array<int, Select>
     */
    private function ratingSelects(): array
    {
        $options = array_combine(range(1, 5), array_map(strval(...), range(1, 5)));

        return array_map(
            fn (string $field): Select => Select::make($field)
                ->label(__('tender_participation_scores.factors.'.$field))
                ->options($options)
                ->native(false),
            TenderParticipationScore::MANUAL_RATING_FIELDS,
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE),
                View::make('filament.relation-managers.participation-score-summary')
                    ->viewData(function (): array {
                        $score = $this->currentOrNewScore();

                        return [
                            'score' => $score,
                            'manualFields' => TenderParticipationScore::MANUAL_RATING_FIELDS,
                            'missingRatingsCount' => count(array_filter(
                                TenderParticipationScore::MANUAL_RATING_FIELDS,
                                fn (string $field): bool => $score->{$field} === null,
                            )),
                        ];
                    }),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('decided_at', 'desc')
            ->columns([
                TextColumn::make('decision')
                    ->label(__('tender_bid_decisions.fields.decision'))
                    ->badge()
                    ->formatStateUsing(fn (BidDecision $state): string => $state->getLabel())
                    ->color(fn (BidDecision $state): string => $state->color()),
                TextColumn::make('score')
                    ->label(__('tender_bid_decisions.fields.score'))
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('tender_bid_decisions.fields.reason'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('decidedBy.name')
                    ->label(__('tender_bid_decisions.fields.decided_by')),
                TextColumn::make('decided_at')
                    ->label(__('tender_bid_decisions.fields.decided_at'))
                    ->dateTime(),
            ])
            ->headerActions([
                Action::make('editScoreInputs')
                    ->label(__('tender_participation_scores.actions.edit_ratings'))
                    ->visible(fn (): bool => $this->canMakeBidDecision())
                    ->before(fn () => abort_unless($this->canMakeBidDecision(), 403))
                    ->schema(fn (): array => $this->ratingSelects())
                    ->fillForm(fn (): array => $this->currentOrNewScore()->only(TenderParticipationScore::MANUAL_RATING_FIELDS))
                    ->action(function (array $data): void {
                        /** @var Tender $tender */
                        $tender = $this->getOwnerRecord();

                        $tender->participationScore()->updateOrCreate([], $data);
                    })
                    ->successNotificationTitle(__('tender_participation_scores.actions.save_success')),
                Action::make('recordDecision')
                    ->label(__('tender_bid_decisions.actions.record_decision'))
                    ->visible(fn (): bool => $this->canMakeBidDecision())
                    ->before(fn () => abort_unless($this->canMakeBidDecision(), 403))
                    ->schema([
                        Select::make('decision')
                            ->label(__('tender_bid_decisions.fields.decision'))
                            ->options(BidDecision::class)
                            ->required()
                            ->live(),
                        Textarea::make('reason')
                            ->label(__('tender_bid_decisions.fields.reason'))
                            ->rows(3)
                            ->required(function (Get $get): bool {
                                $decision = $get('decision');

                                return ($decision instanceof BidDecision ? $decision->value : $decision) === BidDecision::NO_BID->value;
                            }),
                    ])
                    ->action(function (array $data): void {
                        /** @var Tender $tender */
                        $tender = $this->getOwnerRecord();

                        TenderBidDecision::create([
                            'tender_id' => $tender->id,
                            'decision' => $data['decision'],
                            'reason' => $data['reason'] ?? null,
                            'score' => $this->currentOrNewScore()->score(),
                            'decided_by' => auth()->id(),
                            'decided_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle(__('tender_bid_decisions.actions.record_success')),
            ])
            ->recordActions([]);
    }
}
