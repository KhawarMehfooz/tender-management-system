<?php

namespace App\Filament\Widgets;

use App\Models\TenderDeadline;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every tender deadline the current user can see, soonest first — a radar, not the full
 * calendar (TenderCalendar already covers that). Category scoping comes for free from
 * TenderDeadline's own TenderDeadlineCategoryScope. Visible to everyone.
 */
class DeadlineRadarWidget extends TableWidget
{
    protected static ?int $sort = 2;

    public function getTableHeading(): string
    {
        return __('dashboard.deadline_radar.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->paginated(false)
            ->columns([
                TextColumn::make('tender.internal_id')
                    ->label(__('tenders.fields.internal_id')),
                TextColumn::make('tender.title')
                    ->label(__('tenders.fields.title'))
                    ->limit(30),
                TextColumn::make('type')
                    ->label(__('tender_deadlines.fields.type'))
                    ->badge(),
                TextColumn::make('due_at')
                    ->label(__('tender_deadlines.fields.due_at'))
                    ->dateTime(),
            ]);
    }

    /**
     * @return Builder<TenderDeadline>
     */
    private function query(): Builder
    {
        return TenderDeadline::query()
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->with('tender')
            ->limit(10);
    }
}
