<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersByClientChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'client';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('clients', 'clients.id', '=', 'tenders.client_id')
            ->selectRaw('clients.name as label, count(*) as total')
            ->groupBy('clients.name');
    }
}
