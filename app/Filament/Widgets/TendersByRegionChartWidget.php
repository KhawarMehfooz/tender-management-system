<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersByRegionChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'region';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('nuts_codes', 'nuts_codes.id', '=', 'tenders.nuts_code_id')
            ->selectRaw('nuts_codes.label as label, count(*) as total')
            ->groupBy('nuts_codes.label');
    }
}
