<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\CertificateStatus;
use App\Enums\Right;
use App\Models\Certificate;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Attach/detach only — Certificates are created and edited on CertificateResource, never
 * inline here. Gated behind Right::MANAGE_CERTIFICATES like the resource itself (not the
 * broader tender-team gate ReferencesRelationManager/ConceptBlocksRelationManager use):
 * recording which certificate backs a bid is a disqualification-risk decision, not a routine
 * tender-team task, per [[milestones]].
 */
class CertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';

    private function canManage(): bool
    {
        return auth()->user()?->can(Right::MANAGE_CERTIFICATES->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('certificates.fields.name')),
                TextColumn::make('type')
                    ->label(__('certificates.fields.type'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('certificates.fields.status'))
                    ->state(fn (Certificate $record) => $record->status())
                    ->badge()
                    ->color(fn (CertificateStatus $state): string => $state->color()),
                TextColumn::make('expiry_date')
                    ->label(__('certificates.fields.expiry_date'))
                    ->date(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('reference_library.certificates.attach'))
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name']),
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
