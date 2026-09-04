<?php

namespace App\Filament\Resources\NutsCodes\Pages;

use App\Filament\Resources\NutsCodes\NutsCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditNutsCode extends EditRecord
{
    protected static string $resource = NutsCodeResource::class;

    /**
     * No DeleteAction: NUTS codes are deactivated, never deleted.
     * See NutsCodeResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
