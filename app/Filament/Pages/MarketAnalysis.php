<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TendersByClientChartWidget;
use App\Filament\Widgets\TendersByProcurementProcedureChartWidget;
use App\Filament\Widgets\TendersByRegionChartWidget;
use App\Filament\Widgets\TendersBySectorChartWidget;
use App\Filament\Widgets\TendersByServiceCategoryChartWidget;
use App\Filament\Widgets\TendersBySourceChartWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class MarketAnalysis extends Page
{
    protected string $view = 'filament.pages.market-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationLabel(): string
    {
        return __('market_analysis.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.market_intelligence');
    }

    public function getTitle(): string
    {
        return __('market_analysis.title');
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            TendersByRegionChartWidget::class,
            TendersBySectorChartWidget::class,
            TendersByServiceCategoryChartWidget::class,
            TendersByClientChartWidget::class,
            TendersBySourceChartWidget::class,
            TendersByProcurementProcedureChartWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
