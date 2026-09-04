<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Spatie\Permission\Models\Role;

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

    /**
     * Block a save that would leave the system with zero super admins — e.g. the only super
     * admin accidentally changing their own role. Without this, that user (and everyone else)
     * would be locked out of the User administration panel with no way back in, since it's
     * super-admin-only (see UserResource::canManage()) and nothing else can re-grant the role.
     */
    protected function beforeSave(): void
    {
        $data = $this->form->getState();

        /** @var User $record */
        $record = $this->getRecord();

        if (! $record->hasRole(RoleName::SUPER_ADMIN)) {
            return;
        }

        if ($data['role'] === RoleName::SUPER_ADMIN->value) {
            return;
        }

        if (User::role(RoleName::SUPER_ADMIN->value)->count() > 1) {
            return;
        }

        Notification::make()
            ->danger()
            ->title(__('users.validation.last_super_admin'))
            ->send();

        throw new Halt;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        /** @var Role|null $role */
        $role = $record->roles->first();

        $data['role'] = $role?->name;

        $directRights = $record->getDirectPermissions()->pluck('name');

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

        /** @var User $record */
        $record = $this->getRecord();

        $record->syncRoles([RoleName::from($data['role'])->value]);
        $record->syncPermissions(UserForm::selectedRights($data));
    }
}
