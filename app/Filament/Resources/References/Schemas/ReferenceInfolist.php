<?php

namespace App\Filament\Resources\References\Schemas;

use App\Models\Reference;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReferenceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('references.form.details_heading'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextEntry::make('client')
                            ->label(__('references.fields.client')),
                        TextEntry::make('location')
                            ->label(__('references.fields.location'))
                            ->placeholder('-'),
                        TextEntry::make('serviceCategory.name')
                            ->label(__('references.fields.service_category'))
                            ->placeholder('-'),
                        TextEntry::make('sector.name')
                            ->label(__('references.fields.sector'))
                            ->placeholder('-'),
                        TextEntry::make('period_start')
                            ->label(__('references.fields.period_start'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('period_end')
                            ->label(__('references.fields.period_end'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('contract_volume')
                            ->label(__('references.fields.contract_volume'))
                            ->formatStateUsing(fn (Reference $record): string => $record->contract_volume_unknown
                                ? __('references.fields.contract_volume_unknown')
                                : ($record->contract_volume !== null
                                    ? number_format((float) $record->contract_volume, 2).' €'
                                    : '-')),
                        TextEntry::make('headcount')
                            ->label(__('references.fields.headcount'))
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label(__('references.fields.description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('references.form.contact_heading'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        TextEntry::make('contact_person_name')
                            ->label(__('references.fields.contact_person_name'))
                            ->placeholder('-'),
                        TextEntry::make('contact_person_email')
                            ->label(__('references.fields.contact_person_email'))
                            ->placeholder('-'),
                        TextEntry::make('contact_person_phone')
                            ->label(__('references.fields.contact_person_phone'))
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make(__('references.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label(__('references.fields.created_by'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('references.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('references.fields.updated_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
