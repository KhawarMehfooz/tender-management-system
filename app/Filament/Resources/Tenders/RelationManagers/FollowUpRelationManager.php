<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A tender has at most one follow-up record — `tender_follow_ups.tender_id` is unique at the
 * DB level. Same singleton-table pattern as SubmissionRelationManager: CreateAction hides
 * itself once a follow-up record already exists, and the row's own EditAction is the only way
 * to change it afterward. Write access follows the same linkedToDocuments()/canManageTeam()
 * pattern.
 */
class FollowUpRelationManager extends RelationManager
{
    protected static string $relationship = 'followUp';

    private function canManage(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function followUpAlreadyExists(): bool
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $tender->followUp !== null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DateTimePicker::make('presentation_scheduled_at')
                ->label(__('tender_follow_ups.fields.presentation_scheduled_at'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
            DatePicker::make('bid_validity_until')
                ->label(__('tender_follow_ups.fields.bid_validity_until'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
            DatePicker::make('expected_result_date')
                ->label(__('tender_follow_ups.fields.expected_result_date'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
            Textarea::make('presentation_notes')
                ->label(__('tender_follow_ups.fields.presentation_notes'))
                ->columnSpanFull(),
            Textarea::make('negotiation_notes')
                ->label(__('tender_follow_ups.fields.negotiation_notes'))
                ->columnSpanFull(),
            Textarea::make('expected_result_notes')
                ->label(__('tender_follow_ups.fields.expected_result_notes'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('presentation_notes')
            ->columns([
                TextColumn::make('presentation_scheduled_at')
                    ->label(__('tender_follow_ups.fields.presentation_scheduled_at'))
                    ->dateTime(),
                TextColumn::make('bid_validity_until')
                    ->label(__('tender_follow_ups.fields.bid_validity_until'))
                    ->date(),
                TextColumn::make('expected_result_date')
                    ->label(__('tender_follow_ups.fields.expected_result_date'))
                    ->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_follow_ups.actions.new_follow_up'))
                    ->visible(fn (): bool => $this->canManage() && ! $this->followUpAlreadyExists())
                    ->before(fn () => abort_unless($this->canManage() && ! $this->followUpAlreadyExists(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403)),
            ]);
    }
}
