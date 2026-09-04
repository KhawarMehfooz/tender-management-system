<?php

namespace App\Filament\Resources\CpvCodes\Pages;

use App\Filament\Resources\CpvCodes\CpvCodeResource;
use Filament\Resources\Pages\EditRecord;

class EditCpvCode extends EditRecord
{
    protected static string $resource = CpvCodeResource::class;

    /**
     * No DeleteAction: CPV codes are deactivated, never deleted.
     * See CpvCodeResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
