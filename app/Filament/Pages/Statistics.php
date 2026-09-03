<?php

namespace App\Filament\Pages;

use App\Enums\BidDecision;
use App\Enums\CompetitorOutcome;
use App\Enums\Right;
use App\Enums\TenderStatus;
use App\Enums\WinLossReason;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use App\Models\TenderResult;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Statistics extends Page
{
    protected string $view = 'filament.pages.statistics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    public static function getNavigationLabel(): string
    {
        return __('statistics.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.reporting');
    }

    public function getTitle(): string
    {
        return __('statistics.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'canSeePrices' => static::canSeePrices(),
            'formalExclusions' => static::formalExclusions(),
            'winRate' => static::winRate(),
            'participationRate' => static::participationRate(),
            'bidVolume' => static::bidVolume(),
            'wonLostVolume' => static::wonLostVolume(),
            'averageContractValue' => static::canSeePrices() ? static::averageContractValue() : null,
            'averageMargin' => static::canSeePrices() ? static::averageMargin() : null,
            'averageHandlingTimeDays' => static::averageHandlingTimeDays(),
            'deadlineReliability' => static::deadlineReliability(),
            'lossReasons' => static::lossReasonBreakdown(),
            'priceCompetitorDevelopment' => static::priceCompetitorDevelopment(),
        ];
    }

    public static function canSeePrices(): bool
    {
        return auth()->user()?->can(Right::SEE_PRICES->value) ?? false;
    }

    /**
     * Headline KPI, target value zero per idea.md — a bid thrown out on a technicality is pure
     * wasted effort. Rate is against every tender ever created (not just decided ones), since an
     * exclusion can happen at any phase.
     *
     * @return array{count: int, rate: ?float}
     */
    public static function formalExclusions(): array
    {
        $total = Tender::query()->count();
        $excluded = Tender::query()->where('status', TenderStatus::EXCLUDED)->count();

        return [
            'count' => $excluded,
            'rate' => $total === 0 ? null : $excluded / $total,
        ];
    }

    /**
     * WON / (WON + LOST) — decided tenders only, mirrors User::winRate()'s exact definition
     * applied at the whole-portfolio level instead of per employee.
     */
    public static function winRate(): ?float
    {
        $won = Tender::query()->where('status', TenderStatus::WON)->count();
        $lost = Tender::query()->where('status', TenderStatus::LOST)->count();
        $decided = $won + $lost;

        return $decided === 0 ? null : $won / $decided;
    }

    /**
     * Share of bid/no-bid decisions (M6) that came out BID — "reviewed" is approximated as
     * "has a current bid decision recorded" since a tender without one hasn't reached that gate
     * yet.
     */
    public static function participationRate(): ?float
    {
        $decided = Tender::query()->whereHas('currentBidDecision')->count();
        $bid = Tender::query()
            ->whereHas('currentBidDecision', fn (Builder $query) => $query->where('decision', BidDecision::BID))
            ->count();

        return $decided === 0 ? null : $bid / $decided;
    }

    /**
     * @return array{count: int, volume: ?float}
     */
    public static function bidVolume(): array
    {
        $bidTenders = Tender::query()
            ->whereHas('currentBidDecision', fn (Builder $query) => $query->where('decision', BidDecision::BID))
            ->get(['id', 'estimated_contract_volume', 'estimated_contract_volume_unknown']);

        return [
            'count' => $bidTenders->count(),
            'volume' => static::canSeePrices() ? static::sumKnownVolume($bidTenders) : null,
        ];
    }

    /**
     * @return array{wonVolume: ?float, lostVolume: ?float}
     */
    public static function wonLostVolume(): array
    {
        if (! static::canSeePrices()) {
            return ['wonVolume' => null, 'lostVolume' => null];
        }

        $won = Tender::query()->where('status', TenderStatus::WON)
            ->get(['id', 'estimated_contract_volume', 'estimated_contract_volume_unknown']);
        $lost = Tender::query()->where('status', TenderStatus::LOST)
            ->get(['id', 'estimated_contract_volume', 'estimated_contract_volume_unknown']);

        return [
            'wonVolume' => static::sumKnownVolume($won),
            'lostVolume' => static::sumKnownVolume($lost),
        ];
    }

    /**
     * @param  Collection<int, Tender>  $tenders
     */
    private static function sumKnownVolume(Collection $tenders): float
    {
        return (float) $tenders
            ->reject(fn (Tender $tender): bool => $tender->estimated_contract_volume_unknown || $tender->estimated_contract_volume === null)
            ->sum(fn (Tender $tender): float => (float) $tender->estimated_contract_volume);
    }

    public static function averageContractValue(): ?float
    {
        $tenders = Tender::query()
            ->where('estimated_contract_volume_unknown', false)
            ->whereNotNull('estimated_contract_volume')
            ->get(['estimated_contract_volume']);

        return $tenders->isEmpty() ? null : (float) $tenders->avg(fn (Tender $tender): float => (float) $tender->estimated_contract_volume);
    }

    /**
     * Average of each tender's latest calculation's actual_margin. Margin figures are gated
     * behind Right::SEE_PRICES, matching every other margin display in the app (CalculationsRelationManager)
     * rather than the separate, currently-unused Right::SEE_MARGINS case — kept consistent with
     * established convention, not introducing a second gate for the same data.
     */
    public static function averageMargin(): ?float
    {
        $margins = Tender::query()
            ->with('currentCalculation')
            ->get()
            ->map(fn (Tender $tender): ?float => $tender->currentCalculation?->actual_margin === null
                ? null
                : (float) $tender->currentCalculation->actual_margin)
            ->reject(fn (?float $margin): bool => $margin === null);

        return $margins->isEmpty() ? null : $margins->avg();
    }

    /**
     * Average days between a tender's creation and it reaching a terminal status, across every
     * closed tender.
     */
    public static function averageHandlingTimeDays(): ?float
    {
        $closed = Tender::query()
            ->whereIn('status', static::terminalStatuses())
            ->with(['statusChanges' => fn ($query) => $query->latest('changed_at')->limit(1)])
            ->get();

        $durations = $closed
            ->map(function (Tender $tender): ?float {
                $closedAt = $tender->statusChanges->first()?->changed_at;

                return $closedAt === null || $tender->created_at === null
                    ? null
                    : abs($closedAt->diffInHours($tender->created_at)) / 24;
            })
            ->reject(fn (?float $duration): bool => $duration === null);

        return $durations->isEmpty() ? null : $durations->avg();
    }

    /**
     * @return array<int, string>
     */
    private static function terminalStatuses(): array
    {
        return collect(TenderStatus::cases())
            ->filter(fn (TenderStatus $status): bool => $status->isTerminal())
            ->map(fn (TenderStatus $status): string => $status->value)
            ->values()
            ->all();
    }

    /**
     * Submission-deadline reliability: share of recorded submissions made on or before the
     * tender's submission deadline, plus the average number of days ahead of deadline bids are
     * submitted (negative when submitted late).
     *
     * @return array{onTimeRate: ?float, averageDaysAhead: ?float}
     */
    public static function deadlineReliability(): array
    {
        $tenders = Tender::query()
            ->whereHas('submission')
            ->with(['submission', 'deadlines'])
            ->get();

        $daysAhead = $tenders
            ->map(function (Tender $tender): ?float {
                $deadline = $tender->submissionDeadline()?->due_at;
                $submittedAt = $tender->submission?->submission_date;

                return $deadline === null || $submittedAt === null
                    ? null
                    : ((int) $deadline->timestamp - (int) $submittedAt->timestamp) / 86400;
            })
            ->reject(fn (?float $days): bool => $days === null);

        return [
            'onTimeRate' => $daysAhead->isEmpty() ? null : $daysAhead->filter(fn (float $days): bool => $days >= 0)->count() / $daysAhead->count(),
            'averageDaysAhead' => $daysAhead->isEmpty() ? null : $daysAhead->avg(),
        ];
    }

    /**
     * Tally of every WinLossReason recorded across every closed tender's result (M9), regardless
     * of won/lost, since idea.md's win/loss analysis categorizes both outcomes with the same
     * reason set.
     *
     * @return array<int, array{reason: string, count: int}>
     */
    private static function lossReasonBreakdown(): array
    {
        $counts = collect(WinLossReason::cases())->mapWithKeys(fn (WinLossReason $reason): array => [$reason->value => 0]);

        TenderResult::query()->whereNotNull('win_loss_reasons')->get(['win_loss_reasons'])
            ->each(function (TenderResult $result) use (&$counts): void {
                // win_loss_reasons is a plain 'array' cast (raw strings), same as
                // ResultRelationManager's WinLossReason::from($state) usage — not an enum-list cast,
                // despite the model's optimistic PHPDoc. Re-declared as a string list here so
                // phpstan checks against the actual runtime shape instead of the PHPDoc's.
                /** @var list<string> $reasons */
                $reasons = $result->win_loss_reasons ?? [];

                foreach ($reasons as $reason) {
                    if ($counts->has($reason)) {
                        $counts[$reason] = $counts[$reason] + 1;
                    }
                }
            });

        return $counts
            ->map(fn (int $count, string $value): array => ['reason' => WinLossReason::from($value)->getLabel(), 'count' => $count])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Quarterly trend over the last 4 quarters: average bid price (price-gated) from each
     * quarter's tenders' current calculation, and competitor win/loss counts from TenderCompetitor
     * rows created in that quarter. A factual breakdown, not a forecast.
     *
     * @return array<int, array{label: string, averageBidPrice: ?float, weWon: int, theyWon: int}>
     */
    private static function priceCompetitorDevelopment(): array
    {
        $quarters = collect(range(3, 0))->map(fn (int $offset): CarbonInterface => now()->subQuarters($offset)->startOfQuarter());

        return $quarters->map(function (CarbonInterface $start) {
            $end = $start->copy()->endOfQuarter();

            $averageBidPrice = null;
            if (static::canSeePrices()) {
                $prices = Tender::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->with('currentCalculation')
                    ->get()
                    ->map(fn (Tender $tender): ?float => $tender->currentCalculation?->bid_price === null
                        ? null
                        : (float) $tender->currentCalculation->bid_price)
                    ->reject(fn (?float $price): bool => $price === null);

                $averageBidPrice = $prices->isEmpty() ? null : $prices->avg();
            }

            $competitors = TenderCompetitor::query()->whereBetween('created_at', [$start, $end])->get(['outcome']);

            return [
                'label' => 'Q'.$start->quarter.' '.$start->year,
                'averageBidPrice' => $averageBidPrice,
                'weWon' => $competitors->where('outcome', CompetitorOutcome::WE_WON)->count(),
                'theyWon' => $competitors->where('outcome', CompetitorOutcome::THEY_WON)->count(),
            ];
        })->all();
    }
}
