<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Enums\Right;
use App\Filament\Resources\Tenders\TenderResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTender extends EditRecord
{
    protected static string $resource = TenderResource::class;

    /**
     * No DeleteAction: tenders are never hard-deleted.
     * See TenderResource::canDelete()/canDeleteAny().
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Belt-and-braces server-side enforcement of the "see prices" right: even
     * though the price fields are hidden from users without it, never trust
     * that UI visibility alone kept a tampered request from smuggling a value
     * through — see .ai/rules/permissions.md.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->can(Right::SEE_PRICES->value)) {
            unset($data['estimated_contract_volume'], $data['estimated_contract_volume_unknown']);
        }

        return $data;
    }
}
