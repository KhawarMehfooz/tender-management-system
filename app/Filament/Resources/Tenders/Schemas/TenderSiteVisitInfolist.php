<?php

namespace App\Filament\Resources\Tenders\Schemas;

use App\Models\TenderSiteVisitPhoto;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class TenderSiteVisitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    /**
     * Extracted so a RelationManager's ViewAction can pass this array directly to
     * ->schema(), per [[tenders-relation-managers]]'s rule that a generic ViewAction shows
     * the disabled form, not a resource infolist, unless told otherwise.
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('tender_site_visits.infolist.details_heading'))
                ->icon(Heroicon::OutlinedMapPin)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('visit_date')
                            ->label(__('tender_site_visits.fields.visit_date'))
                            ->date(),
                        TextEntry::make('contact_person')
                            ->label(__('tender_site_visits.fields.contact_person'))
                            ->placeholder('—'),
                        TextEntry::make('attendees')
                            ->label(__('tender_site_visits.fields.attendees'))
                            ->columnSpanFull(),
                        TextEntry::make('access_routes')
                            ->label(__('tender_site_visits.fields.access_routes'))
                            ->placeholder('—'),
                        TextEntry::make('parking')
                            ->label(__('tender_site_visits.fields.parking'))
                            ->placeholder('—'),
                        TextEntry::make('areas')
                            ->label(__('tender_site_visits.fields.areas'))
                            ->placeholder('—'),
                        TextEntry::make('risks')
                            ->label(__('tender_site_visits.fields.risks'))
                            ->placeholder('—'),
                        TextEntry::make('technical_particularities')
                            ->label(__('tender_site_visits.fields.technical_particularities'))
                            ->placeholder('—'),
                        TextEntry::make('staffing_requirement')
                            ->label(__('tender_site_visits.fields.staffing_requirement'))
                            ->placeholder('—'),
                        TextEntry::make('competitors_spotted')
                            ->label(__('tender_site_visits.fields.competitors_spotted'))
                            ->placeholder('—'),
                        TextEntry::make('open_questions')
                            ->label(__('tender_site_visits.fields.open_questions'))
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('tender_site_visits.fields.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ]),
            Section::make(__('tender_site_visits.infolist.photos_heading'))
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    RepeatableEntry::make('photos')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make(__('tender_site_visits.actions.download_photo'))->hiddenHeaderLabel(),
                            TableColumn::make(__('tender_site_visits.fields.photo_size')),
                            TableColumn::make(__('tender_site_visits.fields.photo_uploaded_by')),
                            TableColumn::make(__('tender_site_visits.fields.photo_uploaded_at')),
                        ])
                        ->schema([
                            TextEntry::make('original_filename')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->url(fn (TenderSiteVisitPhoto $record): string => $record->downloadUrl())
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
