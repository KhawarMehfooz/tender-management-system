<?php

namespace App\Filament\Pages;

use App\Enums\DeadlineType;
use App\Filament\Widgets\TenderDeadlineCalendarWidget;
use App\Models\Tender;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class TenderCalendar extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getNavigationLabel(): string
    {
        return __('tender_calendar.navigation_label');
    }

    public function getTitle(): string
    {
        return __('tender_calendar.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFiltersFormContentComponent(),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function getFiltersFormContentComponent(): Component
    {
        return EmbeddedSchema::make('filtersForm');
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make(1)
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()));
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('employee_id')
                    ->label(__('tender_calendar.filters.employee'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder(__('tender_calendar.filters.all')),
                Select::make('tender_id')
                    ->label(__('tender_calendar.filters.tender'))
                    ->options(fn (): array => Tender::query()->orderBy('internal_id')->pluck('internal_id', 'id')->all())
                    ->searchable()
                    ->placeholder(__('tender_calendar.filters.all')),
                Select::make('contracting_authority')
                    ->label(__('tender_calendar.filters.contracting_authority'))
                    ->options(fn (): array => Tender::query()
                        ->distinct()
                        ->orderBy('contracting_authority')
                        ->pluck('contracting_authority', 'contracting_authority')
                        ->all())
                    ->searchable()
                    ->placeholder(__('tender_calendar.filters.all')),
                Select::make('deadline_type')
                    ->label(__('tender_calendar.filters.deadline_type'))
                    ->options(DeadlineType::class)
                    ->placeholder(__('tender_calendar.filters.all')),
            ]);
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            TenderDeadlineCalendarWidget::class,
        ];
    }
}
