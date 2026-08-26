<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Resources\Pages\EditRecord;

class EditSource extends EditRecord
{
    protected static string $resource = SourceResource::class;

    /**
     * No DeleteAction: sources are deactivated, never deleted.
     * See SourceResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
