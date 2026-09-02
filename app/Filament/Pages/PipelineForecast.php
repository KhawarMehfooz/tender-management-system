<?php

namespace App\Filament\Pages;

use App\Enums\Right;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Models\Tender;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PipelineForecast extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.pipeline-forecast';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    public static function getNavigationLabel(): string
    {
        return __('pipeline_forecast.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.market_intelligence');
    }

    public function getTitle(): string
    {
        return __('pipeline_forecast.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->pipelineQuery())
            ->columns([
                TextColumn::make('internal_id')
                    ->label(__('tenders.fields.internal_id'))
                    ->searchable(),
                TextColumn::make('title')
                    ->label(__('tenders.fields.title'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('tenders.fields.status'))
                    ->badge(),
                TextColumn::make('contract_start_date')
                    ->label(__('tenders.fields.contract_start_date'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('estimated_contract_volume')
                    ->label(__('tenders.fields.estimated_contract_volume'))
                    ->formatStateUsing(fn (Tender $record): string => self::formatVolume($record))
                    ->visible(fn (): bool => static::canSeePrices()),
                TextColumn::make('win_probability')
                    ->label(__('pipeline_forecast.columns.win_probability'))
                    ->state(fn (Tender $record): ?float => $record->participationScore?->winProbability())
                    ->formatStateUsing(fn (?float $state): string => $state === null
                        ? __('pipeline_forecast.win_probability_unknown')
                        : number_format($state * 100, 0).'%'),
                TextColumn::make('weighted_value')
                    ->label(__('pipeline_forecast.columns.weighted_value'))
                    ->state(fn (Tender $record): ?float => self::weightedValue($record))
                    ->formatStateUsing(fn (?float $state): string => $state === null
                        ? '—'
                        : __('tenders.infolist.money_eur', ['amount' => number_format($state, 2)]))
                    ->visible(fn (): bool => static::canSeePrices()),
                TextColumn::make('resource_check')
                    ->label(__('pipeline_forecast.columns.resource_check'))
                    ->state(fn (Tender $record): string => self::resourceCheckLabel($record))
                    ->badge()
                    ->color(fn (Tender $record): string => $record->teamMembers->pluck('functional_role')->unique()->count() >= count(TeamRole::cases())
                        ? 'success'
                        : 'warning'),
            ]);
    }

    /**
     * @return Builder<Tender>
     */
    private function pipelineQuery(): Builder
    {
        $terminalStatuses = collect(TenderStatus::cases())
            ->filter(fn (TenderStatus $status): bool => $status->isTerminal())
            ->map(fn (TenderStatus $status): string => $status->value);

        return Tender::query()
            ->whereNotIn('status', $terminalStatuses)
            ->with(['participationScore', 'teamMembers']);
    }

    /**
     * The sum of weighted_value across every row in the current pipeline query — shown below
     * the table, gated behind Right::SEE_PRICES the same as the column itself.
     */
    public function totalWeightedPipelineValue(): ?float
    {
        if (! static::canSeePrices()) {
            return null;
        }

        return $this->pipelineQuery()
            ->get()
            ->sum(fn (Tender $tender): float => self::weightedValue($tender) ?? 0.0);
    }

    public static function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    private static function formatVolume(Tender $record): string
    {
        return $record->estimated_contract_volume_unknown
            ? __('tenders.fields.estimated_contract_volume_unknown')
            : __('tenders.infolist.money_eur', ['amount' => number_format((float) $record->estimated_contract_volume, 2)]);
    }

    /**
     * volume x normalized win probability — null whenever either input is unavailable
     * (volume marked unknown, or the participation score is incomplete), rather than treating
     * a missing input as zero.
     */
    private static function weightedValue(Tender $record): ?float
    {
        if ($record->estimated_contract_volume_unknown || $record->estimated_contract_volume === null) {
            return null;
        }

        $probability = $record->participationScore?->winProbability();

        if ($probability === null) {
            return null;
        }

        return (float) $record->estimated_contract_volume * $probability;
    }

    /**
     * Best-effort staffing signal, not a real capacity/recruitment system (none exists yet) —
     * how many of the 5 TeamRole functions already have at least one team member assigned.
     */
    private static function resourceCheckLabel(Tender $record): string
    {
        $covered = $record->teamMembers->pluck('functional_role')->unique()->count();

        return __('pipeline_forecast.resource_check_coverage', [
            'covered' => $covered,
            'total' => count(TeamRole::cases()),
        ]);
    }
}
