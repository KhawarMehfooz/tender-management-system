<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * No DeleteAction: users are never hard-deleted from this panel.
     * See UserResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->getRecord()->roles->first()?->name;

        $directRights = $this->getRecord()->getDirectPermissions()->pluck('name');

        foreach (Right::cases() as $right) {
            $data[$right->value] = $directRights->contains($right->value);
        }

        return $data;
    }

    /**
     * `role` and the per-right toggles aren't columns on `users` — they're persisted via
     * Spatie's role/permission pivots in afterSave(), not through the model's own
     * fillable set.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['role']);

        foreach (Right::cases() as $right) {
            unset($data[$right->value]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->syncRoles([RoleName::from($data['role'])->value]);
        $this->getRecord()->syncPermissions(UserForm::selectedRights($data));
    }
}
