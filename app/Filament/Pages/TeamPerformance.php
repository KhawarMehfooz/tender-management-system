<?php

namespace App\Filament\Pages;

use App\Enums\CalculationApprovalStep;
use App\Enums\Right;
use App\Enums\TaskStatus;
use App\Models\ServiceCategory;
use App\Models\Task;
use App\Models\TaskStatusChange;
use App\Models\TenderCalculationApproval;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TeamPerformance extends Page
{
    protected string $view = 'filament.pages.team-performance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Right::VIEW_EMPLOYEE_STATISTICS->value) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('team_performance.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.people');
    }

    public function getTitle(): string
    {
        return __('team_performance.title');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'departmentBreakdown' => static::departmentBreakdown(),
            'bottleneckBreakdown' => static::bottleneckBreakdown(),
            'rankings' => static::rankings(),
        ];
    }

    /**
     * Every user's blended performance score and (separately) win rate, sorted descending by
     * score — full cross-employee visibility here is fine since the whole page is already gated
     * behind Right::VIEW_EMPLOYEE_STATISTICS in mount()/canAccess().
     *
     * @return array<int, array{name: string, department: string, score: float, winRate: ?float}>
     */
    private static function rankings(): array
    {
        return User::query()
            ->with('serviceCategory')
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'department' => $user->serviceCategory?->name ?? __('users.infolist.no_service_category'),
                'score' => $user->performanceScore(),
                'winRate' => $user->winRate(),
            ])
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * One row per service category, plus a trailing "management" row for users with no
     * service_category_id, per [[scopes-models]]'s null-means-management convention. A category
     * (or the management bucket) with zero tasks is omitted entirely rather than shown as an
     * all-zero row.
     *
     * @return array<int, array{label: string, statusCounts: array<string, int>, total: int, onTimeRate: ?float, correctionLoopRate: ?float}>
     */
    private static function departmentBreakdown(): array
    {
        $rows = ServiceCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceCategory $category): array => static::departmentRow($category->name, $category->id))
            ->push(static::departmentRow(__('users.infolist.no_service_category'), null));

        return $rows->filter(fn (array $row): bool => $row['total'] > 0)->values()->all();
    }

    /**
     * @return array{label: string, statusCounts: array<string, int>, total: int, onTimeRate: ?float, correctionLoopRate: ?float}
     */
    private static function departmentRow(string $label, ?string $categoryId): array
    {
        $tasks = Task::query()
            ->whereHas('owner', fn ($query) => $query->where('service_category_id', $categoryId))
            ->get();

        $statusCounts = collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status): array => [$status->value => $tasks->where('status', $status)->count()])
            ->all();

        $doneTasks = $tasks->where('status', TaskStatus::DONE);

        $onTimeRate = $doneTasks->isEmpty() ? null : $doneTasks->filter(
            fn (Task $task): bool => $task->due_date === null
                || $task->completion_date === null
                || $task->completion_date->lessThanOrEqualTo($task->due_date)
        )->count() / $doneTasks->count();

        $correctionLoops = TaskStatusChange::query()
            ->whereHas('task.owner', fn ($query) => $query->where('service_category_id', $categoryId))
            ->where('from_status', TaskStatus::IN_REVIEW)
            ->where('to_status', TaskStatus::CORRECTION_REQUIRED)
            ->count();

        $correctionLoopRate = $doneTasks->isEmpty() ? null : $correctionLoops / $doneTasks->count();

        return [
            'label' => $label,
            'statusCounts' => $statusCounts,
            'total' => $tasks->count(),
            'onTimeRate' => $onTimeRate,
            'correctionLoopRate' => $correctionLoopRate,
        ];
    }

    /**
     * Average duration per CalculationApprovalStep, from approved_at minus the previous step's
     * approved_at in chain order (or the calculation's created_at for the chain's first step).
     * A step nobody has approved yet is omitted entirely.
     *
     * @return array<int, array{label: string, sampleSize: int, averageDurationDays: ?float}>
     */
    private static function bottleneckBreakdown(): array
    {
        $rows = collect(CalculationApprovalStep::cases())
            ->map(fn (CalculationApprovalStep $step): array => static::bottleneckRow($step));

        return $rows->filter(fn (array $row): bool => $row['sampleSize'] > 0)->values()->all();
    }

    /**
     * @return array{label: string, sampleSize: int, averageDurationDays: ?float}
     */
    private static function bottleneckRow(CalculationApprovalStep $step): array
    {
        $stepsBefore = $step->stepsBefore();
        $previousStep = $stepsBefore === [] ? null : $stepsBefore[array_key_last($stepsBefore)];

        $durations = TenderCalculationApproval::query()
            ->where('step', $step)
            ->whereNotNull('approved_at')
            ->with('calculation.approvals')
            ->get()
            ->map(function (TenderCalculationApproval $approval) use ($previousStep): ?float {
                $start = $previousStep === null
                    ? $approval->calculation->created_at
                    : $approval->calculation->approvals->firstWhere('step', $previousStep)?->approved_at;

                return $start === null ? null : abs($approval->approved_at->diffInHours($start)) / 24;
            })
            ->reject(fn (?float $duration): bool => $duration === null);

        return [
            'label' => $step->getLabel(),
            'sampleSize' => $durations->count(),
            'averageDurationDays' => $durations->isEmpty() ? null : $durations->avg(),
        ];
    }
}
