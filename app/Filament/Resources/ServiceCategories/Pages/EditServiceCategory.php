<?php

namespace App\Filament\Resources\ServiceCategories\Pages;

use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceCategory extends EditRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    /**
     * No DeleteAction: service categories are deactivated, never deleted.
     * See ServiceCategoryResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
