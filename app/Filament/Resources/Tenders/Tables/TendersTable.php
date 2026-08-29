<?php

namespace App\Filament\Resources\Tenders\Tables;

use App\Enums\DeadlineType;
use App\Enums\RoleName;
use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Models\TenderDeadline;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TendersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_id')
                    ->label(__('tenders.fields.internal_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('tenders.fields.title'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('contracting_authority')
                    ->label(__('tenders.fields.contracting_authority'))
                    ->searchable(),
                TextColumn::make('serviceCategory.name')
                    ->label(__('tenders.fields.service_category_id')),
                TextColumn::make('status')
                    ->label(__('tenders.fields.status'))
                    ->badge(),
                IconColumn::make('is_archived')
                    ->label(__('tenders.fields.is_archived'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_invalid')
                    ->label(__('tenders.fields.invalidity_reason'))
                    ->state(fn (Tender $record): bool => $record->isInvalid())
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submission_deadline')
                    ->label(__('tenders.fields.submission_deadline'))
                    ->state(fn (Tender $record) => $record->submissionDeadline()?->due_at)
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        TenderDeadline::query()
                            ->select('due_at')
                            ->whereColumn('tender_deadlines.tender_id', 'tenders.id')
                            ->where('type', DeadlineType::SUBMISSION->value)
                            ->latest('due_at')
                            ->limit(1),
                        $direction,
                    )),
                TextColumn::make('created_at')
                    ->label(__('tenders.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('tenders.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_category_id')
                    ->label(__('tenders.fields.service_category_id'))
                    ->relationship('serviceCategory', 'name'),
                SelectFilter::make('status')
                    ->label(__('tenders.fields.status'))
                    ->options(TenderStatus::class),
                SelectFilter::make('source_id')
                    ->label(__('tenders.fields.source_id'))
                    ->relationship('source', 'name'),
                TernaryFilter::make('is_archived')
                    ->label(__('tenders.fields.is_archived')),
                TernaryFilter::make('is_invalid')
                    ->label(__('tenders.fields.invalidity_reason'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('invalidated_at'),
                        false: fn (Builder $query) => $query->whereNull('invalidated_at'),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('changeStatus')
                        ->label(__('tenders.actions.change_status'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->visible(fn (Tender $record): bool => $record->status->allowedTransitions() !== [])
                        ->modalWidth(Width::Medium)
                        ->schema(fn (Tender $record) => [
                            Select::make('status')
                                ->label(__('tenders.fields.status'))
                                ->prefixIcon(Heroicon::OutlinedFlag)
                                ->options(fn () => collect($record->status->allowedTransitions())
                                    ->reject(fn (TenderStatus $status) => $status === TenderStatus::SUBMISSION && ! ($record->currentCalculation()->first()?->isFullyApproved() ?? false))
                                    ->mapWithKeys(fn (TenderStatus $status) => [$status->value => $status->getLabel()]))
                                ->required(),
                            Textarea::make('reason')
                                ->label(__('tenders.fields.status_change_reason'))
                                ->rows(2),
                        ])
                        ->action(function (Tender $record, array $data): void {
                            $record->changeStatusTo(TenderStatus::from($data['status']), auth()->user(), $data['reason'] ?? null);
                        })
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.change_status_success')),
                        ),
                    Action::make('archive')
                        ->label(__('tenders.actions.archive'))
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->visible(fn (Tender $record): bool => ! $record->is_archived)
                        ->requiresConfirmation()
                        ->action(fn (Tender $record) => $record->archive(auth()->user()))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.archive_success')),
                        ),
                    Action::make('unarchive')
                        ->label(__('tenders.actions.unarchive'))
                        ->icon(Heroicon::OutlinedArchiveBoxXMark)
                        ->color('gray')
                        ->visible(fn (Tender $record): bool => $record->is_archived)
                        ->requiresConfirmation()
                        ->action(fn (Tender $record) => $record->unarchive())
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.unarchive_success')),
                        ),
                    Action::make('markInvalid')
                        ->label(__('tenders.actions.mark_invalid'))
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->visible(fn (Tender $record): bool => ! $record->isInvalid())
                        ->modalWidth(Width::Medium)
                        ->schema([
                            Textarea::make('reason')
                                ->label(__('tenders.fields.invalidity_reason'))
                                ->rows(2)
                                ->required(),
                        ])
                        ->action(fn (Tender $record, array $data) => $record->markInvalid(auth()->user(), $data['reason']))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.mark_invalid_success')),
                        ),
                    Action::make('clearInvalidFlag')
                        ->label(__('tenders.actions.clear_invalid_flag'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('gray')
                        ->visible(fn (Tender $record): bool => $record->isInvalid())
                        ->requiresConfirmation()
                        ->action(fn (Tender $record) => $record->clearInvalidFlag())
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.clear_invalid_flag_success')),
                        ),
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('hardDelete')
                        ->label(__('tenders.actions.hard_delete'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::SUPER_ADMIN) ?? false)
                        ->modalWidth(Width::Medium)
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')
                                ->label(__('tenders.fields.hard_delete_reason'))
                                ->rows(2)
                                ->required(),
                        ])
                        ->action(fn (Tender $record, array $data) => $record->hardDelete(auth()->user(), $data['reason']))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('tenders.actions.hard_delete_success')),
                        ),
                ]),
            ]);
    }
}
