<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Filament\Resources\Tenders\TenderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTenders extends ListRecords
{
    protected static string $resource = TenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewArchive')
                ->label(__('tenders.archive.view_archive'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->url(fn (): string => TenderResource::getUrl('archive')),
            CreateAction::make(),
        ];
    }
}
