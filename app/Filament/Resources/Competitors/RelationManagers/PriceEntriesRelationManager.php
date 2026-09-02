<?php

namespace App\Filament\Resources\Competitors\RelationManagers;

use App\Enums\Right;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sourced price history for a competitor. Idea.md requires a mandatory source on every
 * price entry (compliance reasoning) — enforced here via TextInput::required(), never
 * optional. Append-only: entries can be edited (typo/detail fixes) but never deleted, same
 * shape as CommunicationRelationManager's audit-trail pattern.
 */
class PriceEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceEntries';

    private function canManage(): bool
    {
        return auth()->user()?->can(Right::SEE_COMPETITOR_DATA->value) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('price')
                ->label(__('competitor_price_entries.fields.price'))
                ->prefixIcon(Heroicon::OutlinedBanknotes)
                ->numeric()
                ->required(),
            TextInput::make('source')
                ->label(__('competitor_price_entries.fields.source'))
                ->prefixIcon(Heroicon::OutlinedDocumentText)
                ->helperText(__('competitor_price_entries.fields.source_helper'))
                ->required(),
            DatePicker::make('observed_on')
                ->label(__('competitor_price_entries.fields.observed_on'))
                ->prefixIcon(Heroicon::OutlinedCalendar),
            Textarea::make('context')
                ->label(__('competitor_price_entries.fields.context'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('source')
            ->defaultSort('observed_on', 'desc')
            ->columns([
                TextColumn::make('price')
                    ->label(__('competitor_price_entries.fields.price'))
                    ->money('EUR'),
                TextColumn::make('source')
                    ->label(__('competitor_price_entries.fields.source'))
                    ->searchable(),
                TextColumn::make('observed_on')
                    ->label(__('competitor_price_entries.fields.observed_on'))
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('createdBy.name')
                    ->label(__('competitor_price_entries.fields.created_by')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (): bool => $this->canManage())
                        ->before(fn () => abort_unless($this->canManage(), 403)),
                ]),
            ]);
    }
}
