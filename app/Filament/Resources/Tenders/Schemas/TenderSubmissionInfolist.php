<?php

namespace App\Filament\Resources\Tenders\Schemas;

use App\Models\TenderSubmissionFile;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class TenderSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    /**
     * Extracted so a RelationManager's ViewAction can pass this array directly to
     * ->schema(), per [[tenders-relation-managers]]'s rule that a generic ViewAction shows
     * the disabled form, not a resource infolist, unless told otherwise — the RelationManager's
     * own form() has no room for the files list.
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('tender_submissions.infolist.details_heading'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('submission_date')
                            ->label(__('tender_submissions.fields.submission_date'))
                            ->date(),
                        TextEntry::make('submission_time')
                            ->label(__('tender_submissions.fields.submission_time')),
                        TextEntry::make('responsibleEmployee.name')
                            ->label(__('tender_submissions.fields.responsible_employee_id')),
                        TextEntry::make('portal')
                            ->label(__('tender_submissions.fields.portal')),
                        TextEntry::make('transmission_route')
                            ->label(__('tender_submissions.fields.transmission_route')),
                        IconEntry::make('receipt_confirmed')
                            ->label(__('tender_submissions.fields.receipt_confirmed'))
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label(__('tender_submissions.fields.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ]),
            Section::make(__('tender_submissions.infolist.files_heading'))
                ->icon(Heroicon::OutlinedDocument)
                ->schema([
                    RepeatableEntry::make('files')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make(__('tender_submissions.actions.download_file'))->hiddenHeaderLabel(),
                            TableColumn::make(__('tender_submissions.fields.file_size')),
                            TableColumn::make(__('tender_submissions.fields.file_uploaded_by')),
                            TableColumn::make(__('tender_submissions.fields.file_uploaded_at')),
                        ])
                        ->schema([
                            TextEntry::make('original_filename')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->url(fn (TenderSubmissionFile $record): string => $record->downloadUrl())
                                ->openUrlInNewTab(),
                            TextEntry::make('size')
                                ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                            TextEntry::make('uploadedBy.name'),
                            TextEntry::make('created_at')
                                ->dateTime(),
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }
}
