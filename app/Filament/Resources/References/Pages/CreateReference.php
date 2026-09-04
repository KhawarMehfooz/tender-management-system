<?php

namespace App\Filament\Resources\References\Pages;

use App\Filament\Resources\References\ReferenceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReference extends CreateRecord
{
    protected static string $resource = ReferenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
