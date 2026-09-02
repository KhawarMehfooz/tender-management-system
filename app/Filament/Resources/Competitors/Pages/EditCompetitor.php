<?php

namespace App\Filament\Resources\Competitors\Pages;

use App\Filament\Resources\Competitors\CompetitorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompetitor extends EditRecord
{
    protected static string $resource = CompetitorResource::class;

    /**
     * No explicit ->visible()/->before() wiring needed: canCreate/canEdit/canDelete/
     * canViewAny all resolve to the same canManage() check, so reaching this page at all
     * already proves the actor holds Right::SEE_COMPETITOR_DATA — same reasoning as
     * CertificateResource's bare DeleteAction under its single MANAGE_CERTIFICATES gate.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
