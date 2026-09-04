<?php

namespace App\Filament\Resources\ConceptBlocks\Pages;

use App\Filament\Resources\ConceptBlocks\ConceptBlockResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConceptBlock extends ViewRecord
{
    protected static string $resource = ConceptBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
