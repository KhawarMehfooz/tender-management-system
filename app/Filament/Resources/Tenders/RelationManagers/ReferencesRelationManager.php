<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Attach/detach only — References are created and edited on ReferenceResource, never inline
 * here (see [[milestones]]). Gated the same way DocumentsRelationManager gates uploads: the
 * tender owner/team, or a team-manager role.
 */
class ReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'bidReferences';

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
            ->recordTitleAttribute('client')
            ->columns([
                TextColumn::make('client')
                    ->label(__('references.fields.client')),
                TextColumn::make('serviceCategory.name')
                    ->label(__('references.fields.service_category'))
                    ->placeholder('-'),
                TextColumn::make('sector.name')
                    ->label(__('references.fields.sector'))
                    ->placeholder('-'),
                TextColumn::make('period_end')
                    ->label(__('references.fields.period_end'))
                    ->date()
                    ->placeholder('-'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('reference_library.references.attach'))
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['client']),
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
