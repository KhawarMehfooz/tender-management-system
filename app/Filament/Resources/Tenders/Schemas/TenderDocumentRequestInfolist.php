<?php

namespace App\Filament\Resources\Tenders\Schemas;

use App\Models\TenderDocumentRequestFile;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class TenderDocumentRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    /**
     * Extracted so a RelationManager's ViewAction can pass this array directly to ->schema(),
     * per [[tenders-relation-managers]]'s rule that a generic ViewAction shows the disabled
     * form, not a resource infolist, unless told otherwise.
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('tender_document_requests.infolist.details_heading'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('description')
                            ->label(__('tender_document_requests.fields.description'))
                            ->columnSpanFull(),
                        TextEntry::make('communication.subject')
                            ->label(__('tender_document_requests.fields.tender_communication_id'))
                            ->placeholder('—'),
                        TextEntry::make('owner.name')
                            ->label(__('tender_document_requests.fields.owner_id')),
                        TextEntry::make('deadline')
                            ->label(__('tender_document_requests.fields.deadline'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('tender_document_requests.fields.status'))
                            ->badge(),
                    ]),
                ]),
            Section::make(__('tender_document_requests.infolist.files_heading'))
                ->icon(Heroicon::OutlinedDocument)
                ->schema([
                    RepeatableEntry::make('files')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make(__('tender_document_requests.actions.download_file'))->hiddenHeaderLabel(),
                            TableColumn::make(__('tender_document_requests.fields.file_size')),
                            TableColumn::make(__('tender_document_requests.fields.file_uploaded_by')),
                            TableColumn::make(__('tender_document_requests.fields.file_uploaded_at')),
                        ])
                        ->schema([
                            TextEntry::make('original_filename')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->url(fn (TenderDocumentRequestFile $record): string => $record->downloadUrl())
                                ->openUrlInNewTab(),
                            TextEntry::make('size')
                                ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                            TextEntry::make('uploadedBy.name'),
                            TextEntry::make('created_at')
                                ->dateTime(),
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make(__('tender_document_requests.infolist.status_history_heading'))
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    ViewEntry::make('statusChanges')
                        ->hiddenLabel()
                        ->view('filament.infolists.tender-document-request-status-timeline'),
                ])
                ->visible(fn ($record): bool => $record->statusChanges()->exists()),
        ];
    }
}
