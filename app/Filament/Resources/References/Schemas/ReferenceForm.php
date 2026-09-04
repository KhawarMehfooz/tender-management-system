<?php

namespace App\Filament\Resources\References\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReferenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('references.form.details_heading'))
                    ->description(__('references.form.details_description'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('client')
                                ->label(__('references.fields.client'))
                                ->prefixIcon(Heroicon::OutlinedBuildingOffice)
                                ->required(),
                            TextInput::make('location')
                                ->label(__('references.fields.location'))
                                ->prefixIcon(Heroicon::OutlinedMapPin),
                            Select::make('service_category_id')
                                ->label(__('references.fields.service_category'))
                                ->relationship('serviceCategory', 'name')
                                ->prefixIcon(Heroicon::OutlinedTag)
                                ->searchable()
                                ->preload(),
                            Select::make('sector_id')
                                ->label(__('references.fields.sector'))
                                ->relationship('sector', 'name')
                                ->prefixIcon(Heroicon::OutlinedBriefcase)
                                ->searchable()
                                ->preload(),
                            DatePicker::make('period_start')
                                ->label(__('references.fields.period_start'))
                                ->prefixIcon(Heroicon::OutlinedCalendarDays),
                            DatePicker::make('period_end')
                                ->label(__('references.fields.period_end'))
                                ->prefixIcon(Heroicon::OutlinedCalendarDays),
                            TextInput::make('contract_volume')
                                ->label(__('references.fields.contract_volume'))
                                ->numeric()
                                ->prefix('€')
                                ->disabled(fn (Get $get): bool => (bool) $get('contract_volume_unknown')),
                            Toggle::make('contract_volume_unknown')
                                ->label(__('references.fields.contract_volume_unknown'))
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?bool $state): void {
                                    if ($state) {
                                        $set('contract_volume', null);
                                    }
                                }),
                            TextInput::make('headcount')
                                ->label(__('references.fields.headcount'))
                                ->numeric()
                                ->minValue(0)
                                ->prefixIcon(Heroicon::OutlinedUserGroup),
                        ]),
                        Textarea::make('description')
                            ->label(__('references.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('references.form.contact_heading'))
                    ->description(__('references.form.contact_description'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('contact_person_name')
                                ->label(__('references.fields.contact_person_name'))
                                ->prefixIcon(Heroicon::OutlinedUserCircle),
                            TextInput::make('contact_person_email')
                                ->label(__('references.fields.contact_person_email'))
                                ->email()
                                ->prefixIcon(Heroicon::OutlinedEnvelope),
                            TextInput::make('contact_person_phone')
                                ->label(__('references.fields.contact_person_phone'))
                                ->prefixIcon(Heroicon::OutlinedPhone),
                        ]),
                    ]),
            ]);
    }
}
