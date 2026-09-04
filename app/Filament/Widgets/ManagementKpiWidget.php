<?php

namespace App\Filament\Widgets;

use App\Enums\Right;
use App\Enums\TenderStatus;
use App\Models\Tender;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The "management" cut of the dashboard: headline portfolio KPIs, gated behind
 * Right::VIEW_EMPLOYEE_STATISTICS — the same right TeamPerformance already uses for
 * leadership-level cross-employee visibility.
 */
class ManagementKpiWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('dashboard.management_kpis.heading');
    }

    public static function canView(): bool
    {
        return auth()->user()?->can(Right::VIEW_EMPLOYEE_STATISTICS->value) ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $won = Tender::query()->where('status', TenderStatus::WON)->count();
        $lost = Tender::query()->where('status', TenderStatus::LOST)->count();
        $decided = $won + $lost;
        $winRate = $decided === 0 ? null : $won / $decided;

        $excluded = Tender::query()->where('status', TenderStatus::EXCLUDED)->count();

        $terminalStatuses = collect(TenderStatus::cases())
            ->filter(fn (TenderStatus $status): bool => $status->isTerminal())
            ->map(fn (TenderStatus $status): string => $status->value)
            ->values()
            ->all();
        $openPipeline = Tender::query()->whereNotIn('status', $terminalStatuses)->count();

        return [
            Stat::make(
                __('dashboard.management_kpis.win_rate'),
                $winRate === null ? '—' : number_format($winRate * 100, 1).'%',
            ),
            Stat::make(__('dashboard.management_kpis.formal_exclusions'), $excluded)
                ->color($excluded > 0 ? 'danger' : 'success'),
            Stat::make(__('dashboard.management_kpis.open_pipeline'), $openPipeline),
        ];
    }
}
