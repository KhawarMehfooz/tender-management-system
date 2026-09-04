<?php

namespace App\Filament\Resources\Certificates\Schemas;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CertificateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('certificates.form.details_heading'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('certificates.fields.status'))
                            ->state(fn (Certificate $record) => $record->status())
                            ->badge()
                            ->color(fn (CertificateStatus $state): string => $state->color()),
                        TextEntry::make('type')
                            ->label(__('certificates.fields.type'))
                            ->badge(),
                        TextEntry::make('name')
                            ->label(__('certificates.fields.name')),
                        TextEntry::make('issuing_body')
                            ->label(__('certificates.fields.issuing_body'))
                            ->placeholder('-'),
                        TextEntry::make('valid_from')
                            ->label(__('certificates.fields.valid_from'))
                            ->date(),
                        TextEntry::make('expiry_date')
                            ->label(__('certificates.fields.expiry_date'))
                            ->date(),
                        TextEntry::make('original_filename')
                            ->label(__('certificates.fields.file'))
                            ->placeholder('-')
                            ->url(fn (Certificate $record): ?string => $record->file_path !== null ? $record->downloadUrl() : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('notes')
                            ->label(__('certificates.fields.notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('certificates.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label(__('certificates.fields.created_by'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('certificates.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('certificates.fields.updated_at'))
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
