<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use App\Models\Tender;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Attach/detach only — ConceptBlocks are created and edited on ConceptBlockResource, never
 * inline here. Attaching pins the block's *current* version at that moment
 * (concept_block_version_id) rather than exposing a version picker: idea.md doesn't call for
 * choosing an older version at attach time, only for the pin to exist so a later edit to the
 * block doesn't retroactively change what a past bid used (see [[milestones]]).
 */
class ConceptBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'conceptBlocks';

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label(__('concept_blocks.fields.title')),
                TextColumn::make('category')
                    ->label(__('concept_blocks.fields.category'))
                    ->badge(),
                TextColumn::make('pivot.concept_block_version_id')
                    ->label(__('concept_blocks.fields.version_number'))
                    ->state(fn (ConceptBlock $record): ?int => ConceptBlockVersion::query()
                        ->where('id', $record->pivot?->getAttribute('concept_block_version_id'))
                        ->first()
                        ?->version_number),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('reference_library.concept_blocks.attach'))
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title'])
                    ->action(function (array $data): void {
                        /** @var Tender $tender */
                        $tender = $this->getOwnerRecord();
                        /** @var ConceptBlock $conceptBlock */
                        $conceptBlock = ConceptBlock::query()->findOrFail($data['recordId']);

                        $tender->conceptBlocks()->syncWithoutDetaching([
                            $conceptBlock->id => ['concept_block_version_id' => $conceptBlock->currentVersion?->id],
                        ]);
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403)),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403)),
            ]);
    }
}
