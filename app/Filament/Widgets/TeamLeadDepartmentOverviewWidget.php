<?php

namespace App\Filament\Widgets;

use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The "team lead" cut of the dashboard: a quick open/overdue/on-time snapshot of the current
 * user's own department, gated by role (not Right::VIEW_EMPLOYEE_STATISTICS — that right is
 * only granted to super-admin/department-head by default, per [[pages]], while this widget's
 * figures are operational department visibility, not a sensitive cross-employee ranking, so a
 * plain team lead should see it for their own department too).
 */
class TeamLeadDepartmentOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = null;

    public function getHeading(): string
    {
        return __('dashboard.team_overview.heading');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->service_category_id !== null
            && ($user->hasRole(RoleName::TEAM_LEAD) || $user->hasRole(RoleName::DEPARTMENT_HEAD));
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $categoryId = auth()->user()?->service_category_id;

        $tasks = Task::query()
            ->whereHas('owner', fn ($query) => $query->where('service_category_id', $categoryId))
            ->get();

        $open = $tasks->where('status', '!=', TaskStatus::DONE);
        $overdue = $open->filter(fn (Task $task): bool => $task->due_date !== null && $task->due_date->isPast());
        $done = $tasks->where('status', TaskStatus::DONE);
        $onTime = $done->filter(fn (Task $task): bool => $task->due_date === null
            || $task->completion_date === null
            || $task->completion_date->lessThanOrEqualTo($task->due_date));
        $onTimeRate = $done->isEmpty() ? null : $onTime->count() / $done->count();

        return [
            Stat::make(__('dashboard.team_overview.open_tasks'), $open->count()),
            Stat::make(__('dashboard.team_overview.overdue_tasks'), $overdue->count())
                ->color($overdue->isNotEmpty() ? 'danger' : 'success'),
            Stat::make(
                __('dashboard.team_overview.on_time_rate'),
                $onTimeRate === null ? '—' : number_format($onTimeRate * 100, 1).'%',
            ),
        ];
    }
}
