<?php

namespace App\Filament\Resources\Certificates\Schemas;

use App\Enums\CertificateType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('certificates.form.details_heading'))
                    ->description(__('certificates.form.details_description'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label(__('certificates.fields.type'))
                                ->options(CertificateType::class)
                                ->searchable()
                                ->preload()
                                ->prefixIcon(Heroicon::OutlinedTag)
                                ->required(),
                            TextInput::make('name')
                                ->label(__('certificates.fields.name'))
                                ->prefixIcon(Heroicon::OutlinedShieldCheck)
                                ->required(),
                            TextInput::make('issuing_body')
                                ->label(__('certificates.fields.issuing_body'))
                                ->prefixIcon(Heroicon::OutlinedBuildingOffice),
                            DatePicker::make('valid_from')
                                ->label(__('certificates.fields.valid_from'))
                                ->prefixIcon(Heroicon::OutlinedCalendarDays)
                                ->required(),
                            DatePicker::make('expiry_date')
                                ->label(__('certificates.fields.expiry_date'))
                                ->prefixIcon(Heroicon::OutlinedCalendarDays)
                                ->required()
                                ->afterOrEqual('valid_from'),
                        ]),
                        FileUpload::make('file_path')
                            ->label(__('certificates.fields.file'))
                            ->disk('local')
                            ->directory('certificates')
                            ->preserveFilenames()
                            ->preventFilePathTampering()
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('certificates.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
