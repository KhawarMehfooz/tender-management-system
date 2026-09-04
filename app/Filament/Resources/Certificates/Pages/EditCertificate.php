<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCertificate extends EditRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Certificate $record */
        $record = $this->record;

        if (isset($data['file_path']) && $data['file_path'] !== $record->file_path) {
            $data['original_filename'] = basename((string) $data['file_path']);
            $data['mime_type'] = Storage::disk('local')->mimeType($data['file_path']);
            $data['size'] = Storage::disk('local')->size($data['file_path']);
        }

        return $data;
    }
}
