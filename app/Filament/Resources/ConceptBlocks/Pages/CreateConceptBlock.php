<?php

namespace App\Filament\Resources\ConceptBlocks\Pages;

use App\Filament\Resources\ConceptBlocks\ConceptBlockResource;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use Filament\Resources\Pages\CreateRecord;

class CreateConceptBlock extends CreateRecord
{
    protected static string $resource = ConceptBlockResource::class;

    /**
     * NOT named $content — Filament's page Blade view renders the form via the magic
     * `$this->content` property, which resolves to the content() schema-building method.
     * A real property of that name on this class would shadow it and silently blank the
     * whole page (PHP resolves $this->content to the declared property, never reaching the
     * schema magic getter). Mirrors EditConceptBlock's $newContent naming for the same reason.
     */
    private string $pendingContent = '';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingContent = $data['content'];
        unset($data['content']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ConceptBlock $record */
        $record = $this->record;

        ConceptBlockVersion::create([
            'concept_block_id' => $record->id,
            'version_number' => 1,
            'content' => $this->pendingContent,
            'created_by' => auth()->id(),
        ]);
    }
}
