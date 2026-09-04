<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Filament's base resource page gates every page (including this one) behind
     * UserResource::canViewAny() by default, which would block a plain employee from ever
     * reaching their own profile (canViewAny() is deliberately narrower — super admins and
     * Right::VIEW_EMPLOYEE_STATISTICS holders only, so there's somewhere to browse a *list* of
     * profiles from). Overridden to a no-op: this page's own authorizeAccess() (inherited from
     * ViewRecord) already enforces UserResource::canView($record), which correctly allows
     * self-view regardless of canViewAny().
     */
    public static function authorizeResourceAccess(): void
    {
        //
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
