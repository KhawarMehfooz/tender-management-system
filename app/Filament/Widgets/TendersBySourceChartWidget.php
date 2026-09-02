<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersBySourceChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'source';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->join('sources', 'sources.id', '=', 'tenders.source_id')
            ->selectRaw('sources.name as label, count(*) as total')
            ->groupBy('sources.name');
    }
}
