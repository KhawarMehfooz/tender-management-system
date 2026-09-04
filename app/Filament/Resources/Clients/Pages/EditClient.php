<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * No DeleteAction: clients are deactivated, never deleted.
     * See ClientResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
