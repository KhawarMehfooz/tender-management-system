<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\Right;
use App\Enums\WinLossReason;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\Tender;
use App\Models\TenderResult;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A tender has at most one result record — `tender_results.tender_id` is unique at the DB
 * level, same singleton-shaped RelationManager pattern as SubmissionRelationManager/
 * FollowUpRelationManager. Only creatable once the tender is terminal (per [[tenders]]'s
 * TenderStatus::isTerminal()) — this is a Create-action visibility gate, not a new workflow
 * transition gate. winning_price/our_price/price_gap are gated behind Right::SEE_PRICES like
 * TenderCalculation's cost/margin fields ([[calculations]]); price_gap is never a form input,
 * it's computed server-side from the other two prices in mutateDataUsing.
 */
class ResultRelationManager extends RelationManager
{
    protected static string $relationship = 'result';

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

    private function canCreateResult(): bool
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $this->canManage() && $tender->status->isTerminal() && $tender->result === null;
    }

    private function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function computePriceGap(array $data): array
    {
        $data['price_gap'] = isset($data['winning_price'], $data['our_price'])
            ? round((float) $data['winning_price'] - (float) $data['our_price'], 2)
            : null;

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('winner')
                ->label(__('tender_results.fields.winner'))
                ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
            TextInput::make('our_rank')
                ->label(__('tender_results.fields.our_rank'))
                ->prefixIcon(Heroicon::OutlinedHashtag)
                ->numeric()
                ->minValue(1),
            TextInput::make('winning_price')
                ->label(__('tender_results.fields.winning_price'))
                ->prefixIcon(Heroicon::OutlinedCurrencyEuro)
                ->numeric()
                ->minValue(0)
                ->visible(fn (): bool => $this->canSeePrices()),
            TextInput::make('our_price')
                ->label(__('tender_results.fields.our_price'))
                ->prefixIcon(Heroicon::OutlinedCurrencyEuro)
                ->numeric()
                ->minValue(0)
                ->visible(fn (): bool => $this->canSeePrices()),
            Placeholder::make('price_gap')
                ->label(__('tender_results.fields.price_gap'))
                ->content(fn (?TenderResult $record): string => $record?->price_gap !== null
                    ? number_format((float) $record->price_gap, 2)
                    : __('tender_results.fields.price_gap_unknown'))
                ->visible(fn (?TenderResult $record): bool => $this->canSeePrices() && $record !== null),
            DatePicker::make('award_date')
                ->label(__('tender_results.fields.award_date'))
                ->prefixIcon(Heroicon::OutlinedCalendarDays),
            Select::make('win_loss_reasons')
                ->label(__('tender_results.fields.win_loss_reasons'))
                ->prefixIcon(Heroicon::OutlinedTag)
                ->options(WinLossReason::class)
                ->multiple(),
            Textarea::make('known_evaluation')
                ->label(__('tender_results.fields.known_evaluation'))
                ->columnSpanFull(),
            Textarea::make('reasoning')
                ->label(__('tender_results.fields.reasoning'))
                ->columnSpanFull(),
            Textarea::make('award_decision')
                ->label(__('tender_results.fields.award_decision'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('winner')
            ->columns([
                TextColumn::make('winner')
                    ->label(__('tender_results.fields.winner')),
                TextColumn::make('our_rank')
                    ->label(__('tender_results.fields.our_rank')),
                TextColumn::make('award_date')
                    ->label(__('tender_results.fields.award_date'))
                    ->date(),
                TextColumn::make('winning_price')
                    ->label(__('tender_results.fields.winning_price'))
                    ->money('EUR')
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('our_price')
                    ->label(__('tender_results.fields.our_price'))
                    ->money('EUR')
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('price_gap')
                    ->label(__('tender_results.fields.price_gap'))
                    ->money('EUR')
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('win_loss_reasons')
                    ->label(__('tender_results.fields.win_loss_reasons'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => WinLossReason::from($state)->getLabel()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_results.actions.new_result'))
                    ->visible(fn (): bool => $this->canCreateResult())
                    ->before(fn () => abort_unless($this->canCreateResult(), 403))
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $this->computePriceGap($data);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->before(fn () => abort_unless($this->canManage(), 403))
                    ->mutateDataUsing(fn (array $data): array => $this->computePriceGap($data)),
            ]);
    }
}
