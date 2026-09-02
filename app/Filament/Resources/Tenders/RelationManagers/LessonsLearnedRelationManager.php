<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A tender has at most one lessons-learned record — `tender_lessons_learned.tender_id` is
 * unique at the DB level, same singleton-shaped RelationManager pattern as
 * ResultRelationManager/SubmissionRelationManager. Only creatable once the tender is terminal
 * (per [[tenders]]'s TenderStatus::isTerminal()), matching ResultRelationManager's gate. All 3
 * answers are `->required()` on both create and edit — idea.md's "retained permanently ... not
 * editable away later" is enforced by that ordinary required-field validation (a correction is
 * still allowed via EditAction, blanking an answer is not), not a bespoke immutability
 * mechanism. No delete action is wired at all, same append-only philosophy as
 * CommunicationRelationManager/DocumentRequestsRelationManager.
 */
class LessonsLearnedRelationManager extends RelationManager
{
    protected static string $relationship = 'lessonsLearned';

    private function canManage(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function canCreateLessonsLearned(): bool
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $this->canManage() && $tender->status->isTerminal() && $tender->lessonsLearned === null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('went_well')
                ->label(__('tender_lessons_learned.fields.went_well'))
                ->required()
                ->columnSpanFull(),
            Textarea::make('differently_next_time')
                ->label(__('tender_lessons_learned.fields.differently_next_time'))
                ->required()
                ->columnSpanFull(),
            Textarea::make('process_changes')
                ->label(__('tender_lessons_learned.fields.process_changes'))
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('went_well')
            ->columns([
                TextColumn::make('went_well')
                    ->label(__('tender_lessons_learned.fields.went_well'))
                    ->limit(60),
                TextColumn::make('differently_next_time')
                    ->label(__('tender_lessons_learned.fields.differently_next_time'))
                    ->limit(60),
                TextColumn::make('process_changes')
                    ->label(__('tender_lessons_learned.fields.process_changes'))
                    ->limit(60),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_lessons_learned.actions.new_entry'))
                    ->visible(fn (): bool => $this->canCreateLessonsLearned())
                    ->before(fn () => abort_unless($this->canCreateLessonsLearned(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403)),
            ]);
    }
}
