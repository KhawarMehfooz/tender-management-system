<?php

namespace App\Filament\Widgets;

use App\Models\Tender;
use Illuminate\Database\Eloquent\Builder;

class TendersByServiceCategoryChartWidget extends TenderBreakdownChartWidget
{
    protected function dimensionKey(): string
    {
        return 'service_category';
    }

    /**
     * @param  Builder<Tender>  $query
     * @return Builder<Tender>
     */
    protected function configureQuery(Builder $query): Builder
    {
        return $query
            ->join('service_categories', 'service_categories.id', '=', 'tenders.service_category_id')
            ->selectRaw('service_categories.name as label, count(*) as total')
            ->groupBy('service_categories.name');
    }
}
