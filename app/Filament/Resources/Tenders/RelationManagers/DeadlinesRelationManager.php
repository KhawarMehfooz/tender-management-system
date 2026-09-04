<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\DeadlineType;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use App\Models\UserAbsence;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Gated by TenderForm::canManageTeam() (team lead/department head/super admin) — the same
 * role set that manages the tender's team, since deadline scheduling is the same kind of
 * tender-level management responsibility. Everyone who can see the tender can still browse
 * its deadlines, since the submission deadline must always be visible.
 *
 * DeadlineType::BID_VALIDITY is excluded from the type Select — that row is derived and kept
 * in sync automatically by Tender::syncBidValidityDeadline() (submission due date +
 * bid_validity_days) whenever the tender is saved, so a manually created/edited one would
 * just be silently overwritten. It still shows up read-only in the table once synced.
 */
class DeadlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlines';

    /**
     * @return array<int, DeadlineType>
     */
    private static function manageableTypes(): array
    {
        return array_values(array_filter(
            DeadlineType::cases(),
            fn (DeadlineType $type): bool => $type !== DeadlineType::BID_VALIDITY,
        ));
    }

    /**
     * Non-blocking warning shown under the due-at field when the tender's owner has a known
     * UserAbsence covering the selected date — same rationale as
     * TaskForm::dueDateAbsenceWarning(), applied to the tender's owner since a deadline has no
     * per-row assignee of its own.
     */
    private function dueAtAbsenceWarning(mixed $dueAt): ?string
    {
        if ($dueAt === null || $dueAt === '') {
            return null;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();
        $moment = Carbon::parse($dueAt);

        $absence = $tender->owner?->absences()
            ->get()
            ->first(fn (UserAbsence $absence): bool => $absence->covers($moment));

        return $absence === null ? null : (string) __('tender_deadlines.fields.due_at_absence_warning', [
            'type' => $absence->type->getLabel(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('tender_deadlines.fields.type'))
                    ->options(collect(self::manageableTypes())->mapWithKeys(
                        fn (DeadlineType $type): array => [$type->value => $type->getLabel()],
                    ))
                    ->required()
                    ->disabled(fn (): bool => ! TenderForm::canManageTeam())
                    ->dehydrated(),
                DateTimePicker::make('due_at')
                    ->label(__('tender_deadlines.fields.due_at'))
                    ->required()
                    ->live()
                    ->helperText(fn (mixed $state): ?string => $this->dueAtAbsenceWarning($state))
                    ->disabled(fn (): bool => ! TenderForm::canManageTeam())
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label(__('tender_deadlines.fields.type'))
                    ->badge(),
                TextColumn::make('due_at')
                    ->label(__('tender_deadlines.fields.due_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('escalation_level')
                    ->label(__('tender_deadlines.fields.escalation_level'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('last_escalated_at')
                    ->label(__('tender_deadlines.fields.last_escalated_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_at')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('tender_deadlines.fields.type'))
                    ->options(DeadlineType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => TenderForm::canManageTeam()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (): bool => TenderForm::canManageTeam()),
                    DeleteAction::make()
                        ->visible(fn (): bool => TenderForm::canManageTeam()),
                ]),
            ]);
    }
}
