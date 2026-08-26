<?php

namespace App\Filament\Resources\Tenders\Tables;

use App\Enums\TenderStatus;
use App\Models\Tender;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('submission_deadline')
                    ->label(__('tenders.fields.submission_deadline'))
                    ->dateTime()
                    ->sortable(),
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
            ])
            ->recordActions([
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
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
