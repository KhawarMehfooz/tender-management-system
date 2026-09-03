<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Models\UserAbsence;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserAbsenceCalendarWidget extends CalendarWidget
{
    use InteractsWithPageFilters;

    protected bool $eventClickEnabled = true;

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return UserAbsence::query()
            ->where('starts_at', '<=', $info->end)
            ->where('ends_at', '>=', $info->start)
            ->with(['user', 'coverUser'])
            ->when(
                $this->pageFilters['employee_id'] ?? null,
                fn (Builder $query, string $employeeId) => $query->where('user_id', $employeeId),
            )
            ->get();
    }

    public function updatedPageFilters(): void
    {
        $this->refreshRecords();
    }

    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        /** @var UserAbsence $event */
        $this->redirect(AbsenceResource::getUrl('edit', ['record' => $event]));
    }
}
