<?php

namespace App\Filament\Resources\NutsCodes\Pages;

use App\Filament\Resources\NutsCodes\NutsCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNutsCodes extends ListRecords
{
    protected static string $resource = NutsCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
