<?php

namespace App\Filament\Pages;

use App\Enums\CompetitorOutcome;
use App\Enums\Right;
use App\Enums\TenderStatus;
use App\Exports\ArrayExport;
use App\Models\Competitor;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function getNavigationLabel(): string
    {
        return __('reports.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.reporting');
    }

    public function getTitle(): string
    {
        return __('reports.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'reports' => static::reportDefinitions(),
        ];
    }

    /**
     * One row per report type from idea.md. "competitors" and "performance" are only offered to
     * users holding the same right that gates their source page (SEE_COMPETITOR_DATA /
     * VIEW_EMPLOYEE_STATISTICS) — re-checked again inside the export actions themselves, this
     * only controls whether the row is shown.
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    private static function reportDefinitions(): array
    {
        $reports = [
            ['key' => 'pipeline', 'label' => __('reports.types.pipeline.label'), 'description' => __('reports.types.pipeline.description')],
            ['key' => 'win_loss', 'label' => __('reports.types.win_loss.label'), 'description' => __('reports.types.win_loss.description')],
        ];

        if (static::canSeeCompetitorData()) {
            $reports[] = ['key' => 'competitors', 'label' => __('reports.types.competitors.label'), 'description' => __('reports.types.competitors.description')];
        }

        if (static::canViewEmployeeStatistics()) {
            $reports[] = ['key' => 'performance', 'label' => __('reports.types.performance.label'), 'description' => __('reports.types.performance.description')];
        }

        $reports[] = ['key' => 'deadlines', 'label' => __('reports.types.deadlines.label'), 'description' => __('reports.types.deadlines.description')];
        $reports[] = ['key' => 'management', 'label' => __('reports.types.management.label'), 'description' => __('reports.types.management.description')];

        return $reports;
    }

    public function exportPdfAction(): Action
    {
        return Action::make('exportPdf')
            ->label(__('reports.actions.export_pdf'))
            ->color('danger')
            ->icon(Heroicon::OutlinedDocumentText)
            ->action(function (array $arguments): StreamedResponse {
                $key = (string) $arguments['report'];

                abort_unless(static::canExport($key), 403);

                $pdf = Pdf::loadView('reports.'.str($key)->replace('_', '-'), [
                    'headings' => static::headingsFor($key),
                    'rows' => static::rowsFor($key),
                    'title' => __('reports.types.'.$key.'.label'),
                ]);

                $filename = $key.'-report.pdf';

                return response()->streamDownload(
                    function () use ($pdf): void {
                        echo $pdf->output();
                    },
                    $filename,
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public function exportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label(__('reports.actions.export_excel'))
            ->color('success')
            ->icon(Heroicon::OutlinedTableCells)
            ->action(function (array $arguments): BinaryFileResponse {
                $key = (string) $arguments['report'];

                abort_unless(static::canExport($key), 403);

                return Excel::download(
                    new ArrayExport(static::rowsFor($key), static::headingsFor($key)),
                    $key.'-report.xlsx',
                );
            });
    }

    private static function canExport(string $key): bool
    {
        return match ($key) {
            'competitors' => static::canSeeCompetitorData(),
            'performance' => static::canViewEmployeeStatistics(),
            default => true,
        };
    }

    public static function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    public static function canSeeCompetitorData(): bool
    {
        return auth()->user()?->can(Right::SEE_COMPETITOR_DATA->value) ?? false;
    }

    public static function canViewEmployeeStatistics(): bool
    {
        return auth()->user()?->can(Right::VIEW_EMPLOYEE_STATISTICS->value) ?? false;
    }

    /**
     * @return list<string>
     */
    private static function headingsFor(string $key): array
    {
        return match ($key) {
            'pipeline' => static::canSeePrices()
                ? ['Internal ID', 'Title', 'Status', 'Estimated volume (EUR)', 'Win probability (%)', 'Weighted value (EUR)']
                : ['Internal ID', 'Title', 'Status', 'Win probability (%)'],
            'win_loss' => static::canSeePrices()
                ? ['Internal ID', 'Title', 'Status', 'Winner', 'Win/loss reasons', 'Estimated volume (EUR)']
                : ['Internal ID', 'Title', 'Status', 'Winner', 'Win/loss reasons'],
            'competitors' => ['Competitor', 'Encounters', 'Wins against us', 'Losses to us', 'Most common sector', 'Most common region'],
            'performance' => ['Employee', 'Department', 'Score', 'Win rate (%)'],
            'deadlines' => ['Metric', 'Value'],
            'management' => ['Metric', 'Value'],
            default => [],
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function rowsFor(string $key): array
    {
        return match ($key) {
            'pipeline' => static::pipelineRows(),
            'win_loss' => static::winLossRows(),
            'competitors' => static::competitorRows(),
            'performance' => static::performanceRows(),
            'deadlines' => static::deadlineRows(),
            'management' => static::managementRows(),
            default => [],
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function pipelineRows(): array
    {
        return PipelineForecast::pipelineQuery()->get()->map(function (Tender $tender): array {
            $probability = $tender->participationScore?->winProbability();
            $volume = $tender->estimated_contract_volume_unknown ? null : $tender->estimated_contract_volume;
            $weightedValue = $volume === null || $probability === null ? null : (float) $volume * $probability;

            $row = [
                $tender->internal_id,
                $tender->title,
                $tender->status->getLabel(),
            ];

            if (static::canSeePrices()) {
                $row[] = $volume === null ? null : (float) $volume;
                $row[] = $probability === null ? null : round($probability * 100, 1);
                $row[] = $weightedValue === null ? null : round($weightedValue, 2);
            } else {
                $row[] = $probability === null ? null : round($probability * 100, 1);
            }

            return $row;
        })->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function winLossRows(): array
    {
        return Tender::query()
            ->whereIn('status', [TenderStatus::WON, TenderStatus::LOST])
            ->with('result')
            ->get()
            ->map(function (Tender $tender): array {
                /** @var list<string> $reasons */
                $reasons = $tender->result === null ? [] : ($tender->result->win_loss_reasons ?? []);

                $row = [
                    $tender->internal_id,
                    $tender->title,
                    $tender->status->getLabel(),
                    $tender->result?->winner,
                    implode(', ', $reasons),
                ];

                if (static::canSeePrices()) {
                    $row[] = $tender->estimated_contract_volume_unknown ? null : (float) $tender->estimated_contract_volume;
                }

                return $row;
            })->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function competitorRows(): array
    {
        return Competitor::query()
            ->with(['tenderCompetitors.tender.sector', 'tenderCompetitors.tender.nutsCode'])
            ->get()
            ->map(fn (Competitor $competitor): array => [
                $competitor->name,
                $competitor->tenderCompetitors->count(),
                $competitor->tenderCompetitors->where('outcome', CompetitorOutcome::THEY_WON)->count(),
                $competitor->tenderCompetitors->where('outcome', CompetitorOutcome::WE_WON)->count(),
                static::mostCommon($competitor->tenderCompetitors, fn (TenderCompetitor $tc): ?string => $tc->tender?->sector?->name),
                static::mostCommon($competitor->tenderCompetitors, fn (TenderCompetitor $tc): ?string => $tc->tender?->nutsCode?->label),
            ])->all();
    }

    /**
     * @param  Collection<int, TenderCompetitor>  $tenderCompetitors
     * @param  \Closure(TenderCompetitor): ?string  $value
     */
    private static function mostCommon(Collection $tenderCompetitors, \Closure $value): ?string
    {
        return $tenderCompetitors
            ->map($value)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function performanceRows(): array
    {
        return collect(TeamPerformance::rankings())
            ->map(fn (array $row): array => [
                $row['name'],
                $row['department'],
                round($row['score'], 2),
                $row['winRate'] === null ? null : round($row['winRate'] * 100, 1),
            ])->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function deadlineRows(): array
    {
        $reliability = Statistics::deadlineReliability();

        return [
            ['On-time rate (%)', $reliability['onTimeRate'] === null ? null : round($reliability['onTimeRate'] * 100, 1)],
            ['Average days ahead of deadline', $reliability['averageDaysAhead'] === null ? null : round($reliability['averageDaysAhead'], 1)],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function managementRows(): array
    {
        $formalExclusions = Statistics::formalExclusions();

        $rows = [
            ['Formal exclusions', $formalExclusions['count']],
            ['Win rate (%)', static::percent(Statistics::winRate())],
            ['Participation rate (%)', static::percent(Statistics::participationRate())],
            ['Average handling time (days)', static::round1(Statistics::averageHandlingTimeDays())],
        ];

        if (static::canSeePrices()) {
            $wonLostVolume = Statistics::wonLostVolume();

            $rows[] = ['Average contract value (EUR)', static::round2(Statistics::averageContractValue())];
            $rows[] = ['Average margin (%)', static::round1(Statistics::averageMargin())];
            $rows[] = ['Won volume (EUR)', static::round2($wonLostVolume['wonVolume'])];
            $rows[] = ['Lost volume (EUR)', static::round2($wonLostVolume['lostVolume'])];
        }

        return $rows;
    }

    private static function percent(?float $value): ?float
    {
        return $value === null ? null : round($value * 100, 1);
    }

    private static function round1(?float $value): ?float
    {
        return $value === null ? null : round($value, 1);
    }

    private static function round2(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}
