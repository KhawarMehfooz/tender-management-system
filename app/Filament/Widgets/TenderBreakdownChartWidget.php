<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared doughnut-chart shape for every Market Analysis breakdown: group Tender::query() by one
 * dimension, chart the top 5 values by count, and fold anything past that into a single "Other"
 * slice so a dimension with many distinct values doesn't render dozens of slivers.
 */
abstract class TenderBreakdownChartWidget extends ChartWidget
{
    /**
     * @var list<string>
     */
    private const PALETTE = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#a855f7', '#14b8a6', '#f97316'];

    private const OTHER_COLOR = '#9ca3af';

    protected ?string $maxHeight = '300px';

    abstract protected function dimensionKey(): string;

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    abstract protected function configureQuery(Builder $query): Builder;

    public function getHeading(): string
    {
        return __('market_analysis.breakdowns.'.$this->dimensionKey());
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->configureQuery(Tender::query())
            ->orderByDesc('total')
            ->get()
            ->map(fn (Tender $row): array => [
                'label' => (string) ($row->getAttribute('label') ?? __('market_analysis.unknown_label')),
                'total' => (int) $row->getAttribute('total'),
            ]);

        $top = $rows->take(5);
        $otherTotal = (int) $rows->slice(5)->sum('total');

        $labels = $top->pluck('label')->all();
        $data = $top->pluck('total')->all();
        $colors = array_slice(self::PALETTE, 0, count($labels));

        if ($otherTotal > 0) {
            $labels[] = __('market_analysis.other_label');
            $data[] = $otherTotal;
            $colors[] = self::OTHER_COLOR;
        }

        return [
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $colors,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;

                                return context.label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        JS);
    }
}
