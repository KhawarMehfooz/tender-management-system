<?php

namespace App\Filament\Resources\CpvCodes\Pages;

use App\Filament\Resources\CpvCodes\CpvCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCpvCodes extends ListRecords
{
    protected static string $resource = CpvCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
