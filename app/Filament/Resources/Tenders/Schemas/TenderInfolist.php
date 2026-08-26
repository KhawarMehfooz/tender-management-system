<?php

namespace App\Filament\Resources\Tenders\Schemas;

use App\Enums\Right;
use App\Models\Tender;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TenderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('tenders.infolist.overview_heading'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('internal_id')
                            ->label(__('tenders.fields.internal_id')),
                        TextEntry::make('status')
                            ->label(__('tenders.fields.status'))
                            ->badge(),
                        TextEntry::make('title')
                            ->label(__('tenders.fields.title'))
                            ->columnSpanFull(),
                        TextEntry::make('contracting_authority')
                            ->label(__('tenders.fields.contracting_authority')),
                        TextEntry::make('serviceCategory.name')
                            ->label(__('tenders.fields.service_category_id')),
                        TextEntry::make('sector.name')
                            ->label(__('tenders.fields.sector_id')),
                        TextEntry::make('procurementProcedure.name')
                            ->label(__('tenders.fields.procurement_procedure_id')),
                        TextEntry::make('source.name')
                            ->label(__('tenders.fields.source_id')),
                        TextEntry::make('submission_deadline')
                            ->label(__('tenders.fields.submission_deadline'))
                            ->dateTime(),
                        TextEntry::make('estimated_contract_volume')
                            ->label(__('tenders.fields.estimated_contract_volume'))
                            ->formatStateUsing(fn (Tender $record): string => $record->estimated_contract_volume_unknown
                                ? __('tenders.fields.estimated_contract_volume_unknown')
                                : __('tenders.infolist.money_eur', ['amount' => number_format((float) $record->estimated_contract_volume, 2)]))
                            ->visible(fn (): bool => auth()->user()?->can(Right::SEE_PRICES->value) ?? false),
                    ])
                    ->columns(2),

                Section::make(__('tenders.infolist.team_heading'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        TextEntry::make('owner.name')
                            ->label(__('tenders.fields.owner_id')),
                        RepeatableEntry::make('teamMembers')
                            ->label(__('tenders.fields.team_members'))
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label(__('tenders.fields.team_member_user')),
                                TextEntry::make('functional_role')
                                    ->label(__('tenders.fields.team_member_role'))
                                    ->badge(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Tender $record): bool => $record->teamMembers()->exists()),
                    ])
                    ->columns(2),

                Section::make(__('tenders.infolist.lifecycle_heading'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->schema([
                        TextEntry::make('archived_at')
                            ->label(__('tenders.fields.is_archived'))
                            ->dateTime()
                            ->visible(fn (Tender $record): bool => $record->is_archived),
                        TextEntry::make('invalidity_reason')
                            ->label(__('tenders.fields.invalidity_reason'))
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Tender $record): bool => $record->isInvalid()),
                    ])
                    ->visible(fn (Tender $record): bool => $record->is_archived || $record->isInvalid())
                    ->columns(2),

                Section::make(__('tenders.infolist.status_history_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        ViewEntry::make('statusChanges')
                            ->hiddenLabel()
                            ->view('filament.infolists.tender-status-timeline'),
                    ])
                    ->visible(fn ($record): bool => $record->statusChanges()->exists()),

                Section::make(__('tenders.infolist.meta_heading'))
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('tenders.fields.created_at'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('tenders.fields.updated_at'))
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
