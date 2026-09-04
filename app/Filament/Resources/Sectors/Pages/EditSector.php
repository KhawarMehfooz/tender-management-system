<?php

namespace App\Filament\Resources\Sectors\Pages;

use App\Filament\Resources\Sectors\SectorResource;
use Filament\Resources\Pages\EditRecord;

class EditSector extends EditRecord
{
    protected static string $resource = SectorResource::class;

    /**
     * No DeleteAction: sectors are deactivated, never deleted.
     * See SectorResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
