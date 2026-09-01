<?php

namespace App\Filament\Resources\References\RelationManagers;

use App\Models\ReferenceAttachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')
                ->label(__('references.fields.attachment_file'))
                ->required()
                ->disk('local')
                ->directory('reference-attachments')
                ->preserveFilenames()
                ->preventFilePathTampering(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                TextColumn::make('original_filename')
                    ->label(__('references.fields.attachment_file'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->searchable(),
                TextColumn::make('size')
                    ->label(__('references.fields.attachment_size'))
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('uploadedBy.name')
                    ->label(__('references.fields.attachment_uploaded_by')),
                TextColumn::make('created_at')
                    ->label(__('references.fields.attachment_uploaded_at'))
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $path = $data['file'];

                        return [
                            'file_path' => $path,
                            'original_filename' => basename((string) $path),
                            'mime_type' => Storage::disk('local')->mimeType($path),
                            'size' => Storage::disk('local')->size($path),
                            'uploaded_by' => auth()->id(),
                        ];
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('references.actions.download_attachment'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (ReferenceAttachment $record): string => $record->downloadUrl())
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->before(fn (ReferenceAttachment $record) => Storage::disk('local')->delete($record->file_path)),
            ]);
    }
}
