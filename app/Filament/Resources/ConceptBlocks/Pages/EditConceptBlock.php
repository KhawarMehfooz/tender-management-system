<?php

namespace App\Filament\Resources\ConceptBlocks\Pages;

use App\Filament\Resources\ConceptBlocks\ConceptBlockResource;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Editing never mutates a ConceptBlockVersion row — a changed content field creates a new
 * version instead, mirroring DocumentsRelationManager's upload-new-version action shape and
 * keeping version history immutable by construction (see [[milestones]]).
 */
class EditConceptBlock extends EditRecord
{
    protected static string $resource = ConceptBlockResource::class;

    private ?string $newContent = null;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ConceptBlock $record */
        $record = $this->record;

        $data['content'] = $record->currentVersion?->content;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ConceptBlock $record */
        $record = $this->record;

        if ($data['content'] !== $record->currentVersion?->content) {
            $this->newContent = $data['content'];
        }

        unset($data['content']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->newContent === null) {
            return;
        }

        /** @var ConceptBlock $record */
        $record = $this->record;

        ConceptBlockVersion::create([
            'concept_block_id' => $record->id,
            'version_number' => $record->versions()->max('version_number') + 1,
            'content' => $this->newContent,
            'created_by' => auth()->id(),
        ]);
    }
}
