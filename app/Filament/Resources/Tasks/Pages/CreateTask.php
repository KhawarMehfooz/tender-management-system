<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    /**
     * Belt-and-braces server-side enforcement per .ai/rules/permissions.md: never trust that
     * disabling the owner/reviewer/participants fields in the UI alone kept a tampered request
     * from smuggling a value through.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();

        if (! TaskForm::canManageTask()) {
            $data['owner_id'] = auth()->id();
            $data['reviewer_id'] = null;
        }

        return $data;
    }
}
