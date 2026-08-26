<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Enums\Right;
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

        return $data;
    }
}
