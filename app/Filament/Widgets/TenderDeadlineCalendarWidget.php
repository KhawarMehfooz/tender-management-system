<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tenders\TenderResource;
use App\Models\Tender;
use App\Models\TenderDeadline;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TenderDeadlineCalendarWidget extends CalendarWidget
{
    use InteractsWithPageFilters;

    protected bool $eventClickEnabled = true;

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return TenderDeadline::query()
            ->whereBetween('due_at', [$info->start, $info->end])
            ->with('tender')
            ->when(
                $this->pageFilters['tender_id'] ?? null,
                fn (Builder $query, string $tenderId) => $query->where('tender_id', $tenderId),
            )
            ->when(
                $this->pageFilters['deadline_type'] ?? null,
                fn (Builder $query, string $type) => $query->where('type', $type),
            )
            ->when(
                $this->pageFilters['contracting_authority'] ?? null,
                fn (Builder $query, string $authority) => $query->whereRelation('tender', 'contracting_authority', $authority),
            )
            ->when(
                $this->pageFilters['employee_id'] ?? null,
                fn (Builder $query, string $employeeId) => $query->whereHas(
                    'tender',
                    fn (Builder $tenderQuery) => $tenderQuery
                        ->where('owner_id', $employeeId)
                        ->orWhereHas('teamMembers', fn (Builder $teamQuery) => $teamQuery->where('user_id', $employeeId)),
                ),
            )
            ->get();
    }

    public function updatedPageFilters(): void
    {
        $this->refreshRecords();
    }

    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        /** @var TenderDeadline $event */
        $tender = Tender::query()->find($event->tender_id);

        if (! $tender) {
            return;
        }

        $this->redirect(TenderResource::getUrl('view', ['record' => $tender]));
    }
}
