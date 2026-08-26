<?php

namespace App\Filament\Resources\CpvCodes\Pages;

use App\Filament\Resources\CpvCodes\CpvCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCpvCode extends CreateRecord
{
    protected static string $resource = CpvCodeResource::class;
}
