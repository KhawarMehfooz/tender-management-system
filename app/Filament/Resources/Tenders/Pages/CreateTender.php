<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Enums\Right;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Filament\Resources\Tenders\TenderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTender extends CreateRecord
{
    protected static string $resource = TenderResource::class;

    /**
     * Belt-and-braces server-side enforcement of the "see prices" right: even
     * though the price fields are hidden from users without it, never trust
     * that UI visibility alone kept a tampered request from smuggling a value
     * through — see .ai/rules/permissions.md.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->can(Right::SEE_PRICES->value)) {
            unset($data['estimated_contract_volume'], $data['estimated_contract_volume_unknown']);
        }

        if ($categoryId = auth()->user()?->service_category_id) {
            $data['service_category_id'] = $categoryId;
        }

        /**
         * Belt-and-braces per [[resources-tenders]]/[[permissions]]: the owner select is
         * disabled in the UI for anyone without team-assignment rights, but never trust that
         * alone — force the owner back to the creating user regardless of what was submitted.
         */
        if (! TenderForm::canManageTeam()) {
            $data['owner_id'] = auth()->id();
        }

        return $data;
    }
}
