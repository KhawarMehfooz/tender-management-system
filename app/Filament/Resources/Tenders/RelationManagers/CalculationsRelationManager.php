<?php

namespace App\Filament\Resources\Tenders\RelationManagers;

use App\Enums\CalculationApprovalStep;
use App\Enums\CostDriverFieldType;
use App\Enums\Right;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Models\ServiceCategoryCostDriverField;
use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\TenderCalculationApproval;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Calculation versions are immutable once created (mirrors TenderDocumentVersion) — there is no
 * edit/delete action, only "new calculation" and "duplicate" (pre-filling a new version's inputs
 * from an existing one). Cost-driver inputs and computed outputs are gated behind the see-prices
 * right (per [[documents]]'s "only CALCULATION is see-prices gated" rule), independently of the
 * 6-step approval chain, which is gated purely by tender_team_members functional role /
 * Right::EXECUTE_FINAL_SUBMISSION on TenderCalculation::approve() — a QUALITY_CONTROL team member
 * without see-prices still needs to be able to approve their step, so the tab itself stays
 * visible to anyone with tender access; only the financial columns/sections are hidden.
 */
class CalculationsRelationManager extends RelationManager
{
    protected static string $relationship = 'calculations';

    private function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    private function calculationModelConfigured(): bool
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $tender->serviceCategory?->calculation_model !== null;
    }

    private function canManageCalculations(): bool
    {
        $user = auth()->user();

        if ($user === null || ! $this->canSeePrices()) {
            return false;
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return TenderForm::canManageTeam() || $tender->linkedToDocuments($user);
    }

    private function nextStepFor(TenderCalculation $record): ?CalculationApprovalStep
    {
        $approvedSteps = $record->approvals()->whereNotNull('approved_at')->pluck('step')->all();

        foreach (CalculationApprovalStep::cases() as $step) {
            if (! in_array($step, $approvedSteps, true)) {
                return $step;
            }
        }

        return null;
    }

    private function canApproveStep(CalculationApprovalStep $step): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $teamRole = $step->teamRole();

        if ($teamRole === null) {
            return $user->can(Right::EXECUTE_FINAL_SUBMISSION->value);
        }

        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();

        return $tender->teamMembers()->where('user_id', $user->id)->where('functional_role', $teamRole)->exists();
    }

    /**
     * The 3 standard margin/risk fields every calculation model needs (see [[milestones]]'s
     * m5-calculation-approvals.md) — split into their own section so the form/infolist reads as
     * "what does the job cost" vs. "what do we charge on top", rather than one flat field list.
     *
     * @var array<int, string>
     */
    private const array MARGIN_FIELD_KEYS = ['target_margin_pct', 'min_margin_pct', 'risk_surcharge_pct'];

    /**
     * A small heuristic from the field's unit/type to a fitting prefix icon, since cost-driver
     * fields are admin-configured per category (see CostDriverFieldsRelationManager) and have no
     * fixed icon of their own to draw on.
     */
    private function iconForField(ServiceCategoryCostDriverField $field): Heroicon
    {
        return match (true) {
            $field->unit === '%' => Heroicon::OutlinedReceiptPercent,
            $field->unit === 'h' => Heroicon::OutlinedClock,
            $field->unit === 'm²' => Heroicon::OutlinedSquares2x2,
            $field->unit === '€' || $field->unit === '€/h' => Heroicon::OutlinedBanknotes,
            $field->type === CostDriverFieldType::TEXT => Heroicon::OutlinedPencil,
            default => Heroicon::OutlinedHashtag,
        };
    }

    /**
     * @return array<int, Section>
     */
    private function costDriverFieldComponents(): array
    {
        /** @var Tender $tender */
        $tender = $this->getOwnerRecord();
        [$marginFields, $costFields] = $tender->serviceCategory->costDriverFields
            ->partition(fn (ServiceCategoryCostDriverField $field): bool => in_array($field->field_key, self::MARGIN_FIELD_KEYS, true));

        return array_filter([
            $costFields->isEmpty() ? null : Section::make(__('tender_calculations.sections.cost_inputs'))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->columns(2)
                ->schema($this->costDriverInputs($costFields)),
            Section::make(__('tender_calculations.sections.margin_inputs'))
                ->icon(Heroicon::OutlinedScale)
                ->columns(2)
                ->schema($this->costDriverInputs($marginFields)),
        ]);
    }

    /**
     * @param  Collection<int, ServiceCategoryCostDriverField>  $fields
     * @return array<int, TextInput>
     */
    private function costDriverInputs(Collection $fields): array
    {
        return $fields->map(function (ServiceCategoryCostDriverField $field): TextInput {
            $input = TextInput::make("input_values.{$field->field_key}")
                ->label($field->label)
                ->prefixIcon($this->iconForField($field))
                ->required($field->required);

            if ($field->unit !== null) {
                $input->suffix($field->unit);
            }

            if ($field->type !== CostDriverFieldType::TEXT) {
                $input->numeric();

                if ($field->type === CostDriverFieldType::DECIMAL) {
                    $input->step(0.01);
                }
            }

            return $input;
        })->values()->all();
    }

    /**
     * @return array<int, Section>
     */
    private function inputValueSections(TenderCalculation $record): array
    {
        [$marginFields, $costFields] = $record->tender->serviceCategory->costDriverFields
            ->partition(fn (ServiceCategoryCostDriverField $field): bool => in_array($field->field_key, self::MARGIN_FIELD_KEYS, true));

        return array_filter([
            $costFields->isEmpty() ? null : Section::make(__('tender_calculations.sections.cost_inputs'))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->visible(fn (): bool => $this->canSeePrices())
                ->columns(2)
                ->schema($this->inputValueEntries($costFields)),
            Section::make(__('tender_calculations.sections.margin_inputs'))
                ->icon(Heroicon::OutlinedScale)
                ->visible(fn (): bool => $this->canSeePrices())
                ->columns(2)
                ->schema($this->inputValueEntries($marginFields)),
        ]);
    }

    /**
     * @param  Collection<int, ServiceCategoryCostDriverField>  $fields
     * @return array<int, TextEntry>
     */
    private function inputValueEntries(Collection $fields): array
    {
        return $fields->map(fn (ServiceCategoryCostDriverField $field): TextEntry => TextEntry::make("input_values.{$field->field_key}")
            ->label($field->label)
            ->icon($this->iconForField($field))
            ->suffix($field->unit)
            ->placeholder('—'))
            ->values()
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version_number', 'desc')
            ->columns([
                TextColumn::make('version_number')
                    ->label(__('tender_calculations.fields.version_number'))
                    ->formatStateUsing(fn (int $state): string => "v{$state}")
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label(__('tender_calculations.fields.created_by')),
                TextColumn::make('created_at')
                    ->label(__('tender_calculations.fields.created_at'))
                    ->dateTime(),
                TextColumn::make('bid_price')
                    ->label(__('tender_calculations.fields.bid_price'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)]))
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('target_margin')
                    ->label(__('tender_calculations.fields.target_margin'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : number_format((float) $state, 1).'%')
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('actual_margin')
                    ->label(__('tender_calculations.fields.actual_margin'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : number_format((float) $state, 1).'%')
                    ->color(fn (?TenderCalculation $record): string => $record !== null
                        && $record->actual_margin !== null
                        && $record->min_margin !== null
                        && (float) $record->actual_margin < (float) $record->min_margin
                            ? 'danger'
                            : 'success')
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('break_even')
                    ->label(__('tender_calculations.fields.break_even'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)]))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => $this->canSeePrices()),
                TextColumn::make('risk_surcharge')
                    ->label(__('tender_calculations.fields.risk_surcharge'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)]))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => $this->canSeePrices()),
            ])
            ->emptyStateDescription(fn (): ?string => $this->calculationModelConfigured() ? null : (string) __('tender_calculations.actions.no_calculation_model'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('tender_calculations.actions.new_calculation'))
                    ->modalWidth(Width::TwoExtraLarge)
                    ->visible(fn (): bool => $this->canManageCalculations() && $this->calculationModelConfigured())
                    ->before(fn () => abort_unless($this->canManageCalculations() && $this->calculationModelConfigured(), 403))
                    ->schema(fn (): array => $this->costDriverFieldComponents())
                    ->mutateDataUsing(function (array $data): array {
                        /** @var Tender $tender */
                        $tender = $this->getOwnerRecord();

                        $data['version_number'] = $tender->calculations()->max('version_number') + 1;
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->after(fn (TenderCalculation $record) => $record->computeOutputs()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth(Width::FourExtraLarge)
                        ->schema(fn (TenderCalculation $record): array => [
                            ...$this->inputValueSections($record),
                            Section::make(__('tender_calculations.sections.results'))
                                ->icon(Heroicon::OutlinedBanknotes)
                                ->visible(fn (): bool => $this->canSeePrices())
                                ->columns(2)
                                ->schema([
                                    TextEntry::make('bid_price')
                                        ->label(__('tender_calculations.fields.bid_price'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)])),
                                    TextEntry::make('unit_price')
                                        ->label(__('tender_calculations.fields.unit_price'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)])),
                                    TextEntry::make('min_margin')
                                        ->label(__('tender_calculations.fields.min_margin'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : number_format((float) $state, 1).'%'),
                                    TextEntry::make('target_margin')
                                        ->label(__('tender_calculations.fields.target_margin'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : number_format((float) $state, 1).'%'),
                                    TextEntry::make('actual_margin')
                                        ->label(__('tender_calculations.fields.actual_margin'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : number_format((float) $state, 1).'%'),
                                    TextEntry::make('break_even')
                                        ->label(__('tender_calculations.fields.break_even'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)])),
                                    TextEntry::make('risk_surcharge')
                                        ->label(__('tender_calculations.fields.risk_surcharge'))
                                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : __('tender_calculations.infolist.money_eur', ['amount' => number_format((float) $state, 2)])),
                                ]),
                            Section::make(__('tender_calculations.sections.formula'))
                                ->icon(Heroicon::OutlinedCalculator)
                                ->visible(fn (): bool => $this->canSeePrices())
                                ->collapsed()
                                ->schema([
                                    TextEntry::make('formula')
                                        ->hiddenLabel()
                                        ->getStateUsing(fn (TenderCalculation $record): array => $record->tender->serviceCategory->calculation_model?->formulaSteps() ?? [])
                                        ->bulleted(),
                                ]),
                            Section::make(__('tender_calculations.sections.approval_chain'))
                                ->icon(Heroicon::OutlinedCheckBadge)
                                ->schema([
                                    RepeatableEntry::make('approval_timeline')
                                        ->hiddenLabel()
                                        ->getStateUsing(fn (TenderCalculation $record): array => $record->approvalTimeline())
                                        ->columns(4)
                                        ->schema([
                                            TextEntry::make('step')
                                                ->label(__('tender_calculations.fields.step'))
                                                ->formatStateUsing(fn (CalculationApprovalStep $state): string => $state->getLabel()),
                                            TextEntry::make('approval')
                                                ->label(__('tender_calculations.fields.status'))
                                                ->badge()
                                                ->formatStateUsing(fn (?TenderCalculationApproval $state): string => $state !== null
                                                    ? __('tender_calculations.approval_status.approved')
                                                    : __('tender_calculations.approval_status.pending'))
                                                ->color(fn (?TenderCalculationApproval $state): string => $state !== null ? 'success' : 'gray'),
                                            TextEntry::make('approval.approvedBy.name')
                                                ->label(__('tender_calculations.fields.approved_by'))
                                                ->placeholder('—'),
                                            TextEntry::make('approval.approved_at')
                                                ->label(__('tender_calculations.fields.approved_at'))
                                                ->dateTime()
                                                ->placeholder('—'),
                                            TextEntry::make('approval.comment')
                                                ->label(__('tender_calculations.fields.comment'))
                                                ->placeholder('—')
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                        ]),
                    Action::make('duplicate')
                        ->label(__('tender_calculations.actions.duplicate'))
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->modalWidth(Width::TwoExtraLarge)
                        ->visible(fn (): bool => $this->canManageCalculations() && $this->calculationModelConfigured())
                        ->before(fn () => abort_unless($this->canManageCalculations() && $this->calculationModelConfigured(), 403))
                        ->schema(fn (): array => $this->costDriverFieldComponents())
                        ->fillForm(fn (TenderCalculation $record): array => ['input_values' => $record->input_values])
                        ->action(function (TenderCalculation $record, array $data): void {
                            /** @var Tender $tender */
                            $tender = $this->getOwnerRecord();

                            $new = TenderCalculation::create([
                                'tender_id' => $tender->id,
                                'version_number' => $tender->calculations()->max('version_number') + 1,
                                'created_by' => auth()->id(),
                                'input_values' => $data['input_values'] ?? [],
                            ]);

                            $new->computeOutputs();
                        }),
                    Action::make('approveNextStep')
                        ->label(fn (TenderCalculation $record): string => __('tender_calculations.actions.approve_step', [
                            'step' => $this->nextStepFor($record)?->getLabel() ?? '',
                        ]))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->schema([
                            Textarea::make('comment')
                                ->label(__('tender_calculations.fields.comment'))
                                ->rows(2),
                        ])
                        ->visible(function (TenderCalculation $record): bool {
                            $step = $this->nextStepFor($record);

                            return $step !== null && $this->canApproveStep($step);
                        })
                        ->before(function (TenderCalculation $record): void {
                            $step = $this->nextStepFor($record);

                            abort_unless($step !== null && $this->canApproveStep($step), 403);
                        })
                        ->action(function (TenderCalculation $record, array $data): void {
                            $step = $this->nextStepFor($record);

                            abort_if($step === null, 403);

                            $record->approve($step, auth()->user(), $data['comment'] ?? null);
                        })
                        ->successNotificationTitle(__('tender_calculations.actions.approve_success')),
                ]),
            ]);
    }
}
