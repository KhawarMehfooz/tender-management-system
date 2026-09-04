<?php

namespace App\Filament\Resources\ProcurementProcedures\Pages;

use App\Filament\Resources\ProcurementProcedures\ProcurementProcedureResource;
use Filament\Resources\Pages\EditRecord;

class EditProcurementProcedure extends EditRecord
{
    protected static string $resource = ProcurementProcedureResource::class;

    /**
     * No DeleteAction: procurement procedures are deactivated, never deleted.
     * See ProcurementProcedureResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
