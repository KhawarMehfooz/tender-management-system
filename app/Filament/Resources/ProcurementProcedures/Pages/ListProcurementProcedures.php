<?php

namespace App\Filament\Resources\ProcurementProcedures\Pages;

use App\Filament\Resources\ProcurementProcedures\ProcurementProcedureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcurementProcedures extends ListRecords
{
    protected static string $resource = ProcurementProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
