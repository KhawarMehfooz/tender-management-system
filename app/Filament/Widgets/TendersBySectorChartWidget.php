<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersBySectorChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'sector';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->join('sectors', 'sectors.id', '=', 'tenders.sector_id')
            ->selectRaw('sectors.name as label, count(*) as total')
            ->groupBy('sectors.name');
    }
}
