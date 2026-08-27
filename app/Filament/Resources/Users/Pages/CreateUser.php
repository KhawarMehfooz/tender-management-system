<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * `role` and the per-right toggles aren't columns on `users` — they're persisted via
     * Spatie's role/permission pivots in afterCreate(), not through the model's own
     * fillable set.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['role']);

        foreach (Right::cases() as $right) {
            unset($data[$right->value]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->assignRole(RoleName::from($data['role'])->value);
        $this->getRecord()->givePermissionTo(UserForm::selectedRights($data));
    }
}
