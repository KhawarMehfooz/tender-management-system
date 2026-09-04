<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "employee" cut of the dashboard: the current user's own open tasks, soonest due date
 * first. Visible to everyone — self-service, not gated behind any right.
 */
class EmployeeOpenTasksWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('dashboard.employee_open_tasks.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label(__('tasks.fields.title'))
                    ->limit(40),
                TextColumn::make('tender.title')
                    ->label(__('tenders.fields.title'))
                    ->limit(30),
                TextColumn::make('status')
                    ->label(__('tasks.fields.status'))
                    ->badge(),
                TextColumn::make('priority')
                    ->label(__('tasks.fields.priority'))
                    ->badge(),
                TextColumn::make('due_date')
                    ->label(__('tasks.fields.due_date'))
                    ->date()
                    ->placeholder('—'),
            ]);
    }

    /**
     * @return Builder<Task>
     */
    private function query(): Builder
    {
        return Task::query()
            ->where('owner_id', auth()->id())
            ->where('status', '!=', TaskStatus::DONE)
            ->orderByRaw('due_date IS NULL, due_date asc')
            ->limit(10);
    }
}
