<?php

namespace App\Filament\Resources\ConceptBlocks\Pages;

use App\Filament\Resources\ConceptBlocks\ConceptBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConceptBlocks extends ListRecords
{
    protected static string $resource = ConceptBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
