<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    /**
     * No DeleteAction: tasks are never hard-deleted. See
     * TaskResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Belt-and-braces server-side enforcement per .ai/rules/permissions.md: never trust that
     * disabling the owner/reviewer/participants fields in the UI alone kept a tampered request
     * from smuggling a value through.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! TaskForm::canManageTask()) {
            $data['owner_id'] = $this->getRecord()->owner_id;
            $data['reviewer_id'] = $this->getRecord()->reviewer_id;
        }

        return $data;
    }
}
