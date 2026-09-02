<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersByProcurementProcedureChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'procurement_procedure';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->join('procurement_procedures', 'procurement_procedures.id', '=', 'tenders.procurement_procedure_id')
            ->selectRaw('procurement_procedures.name as label, count(*) as total')
            ->groupBy('procurement_procedures.name');
    }
}
