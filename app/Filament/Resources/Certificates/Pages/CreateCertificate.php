<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateCertificate extends CreateRecord
{
    protected static string $resource = CertificateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (isset($data['file_path'])) {
            $data['original_filename'] = basename((string) $data['file_path']);
            $data['mime_type'] = Storage::disk('local')->mimeType($data['file_path']);
            $data['size'] = Storage::disk('local')->size($data['file_path']);
        }

        return $data;
    }
}
