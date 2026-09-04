<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tenders\TenderResource;
use App\Models\TaskStatusChange;
use App\Models\TenderDocumentRequestStatusChange;
use App\Models\TenderStatusChange;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * A reverse-chronological feed merging the app's existing per-entity status-change logs
 * (TenderStatusChange, TaskStatusChange, TenderDocumentRequestStatusChange) — no new "activity
 * log" model, this is a read-time union over logs that already exist. Category scoping is
 * applied manually per source (none of these three models carry their own global scope), since
 * they're queried directly rather than through their parent Tender relation. Visible to everyone.
 */
class ActivityFeedWidget extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.activity-feed-widget';

    private const int LIMIT = 15;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['entries' => $this->entries()];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(): Collection
    {
        $categoryId = auth()->user()?->service_category_id;

        $taskChanges = TaskStatusChange::query()
            ->when($categoryId, fn ($query) => $query->whereHas('task.tender', fn ($query) => $query->where('service_category_id', $categoryId)))
            ->with(['task.tender', 'changedBy'])
            ->latest('changed_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (TaskStatusChange $change): array => [
                'changedAt' => $change->changed_at,
                'actor' => $change->changedBy?->name,
                'summary' => (string) __('dashboard.activity_feed.task_status_changed', [
                    'task' => $change->task?->title,
                    'from' => $change->from_status->getLabel(),
                    'to' => $change->to_status->getLabel(),
                ]),
                'url' => $change->task?->tender_id ? TenderResource::getUrl('view', ['record' => $change->task->tender_id]) : null,
            ]);

        $tenderChanges = TenderStatusChange::query()
            ->when($categoryId, fn ($query) => $query->whereHas('tender', fn ($query) => $query->where('service_category_id', $categoryId)))
            ->with(['tender', 'changedBy'])
            ->latest('changed_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (TenderStatusChange $change): array => [
                'changedAt' => $change->changed_at,
                'actor' => $change->changedBy?->name,
                'summary' => (string) __('dashboard.activity_feed.tender_status_changed', [
                    'tender' => $change->tender?->title,
                    'from' => $change->from_status->getLabel(),
                    'to' => $change->to_status->getLabel(),
                ]),
                'url' => $change->tender_id ? TenderResource::getUrl('view', ['record' => $change->tender_id]) : null,
            ]);

        $documentRequestChanges = TenderDocumentRequestStatusChange::query()
            ->when($categoryId, fn ($query) => $query->whereHas('documentRequest.tender', fn ($query) => $query->where('service_category_id', $categoryId)))
            ->with(['documentRequest.tender', 'changedBy'])
            ->latest('changed_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (TenderDocumentRequestStatusChange $change): array => [
                'changedAt' => $change->changed_at,
                'actor' => $change->changedBy?->name,
                'summary' => (string) __('dashboard.activity_feed.document_request_status_changed', [
                    'description' => $change->documentRequest?->description,
                    'to' => $change->to_status->getLabel(),
                ]),
                'url' => $change->documentRequest?->tender_id ? TenderResource::getUrl('view', ['record' => $change->documentRequest->tender_id]) : null,
            ]);

        /** @var Collection<int, array<string, mixed>> $merged */
        $merged = $taskChanges
            ->concat($tenderChanges)
            ->concat($documentRequestChanges)
            ->sortByDesc('changedAt')
            ->take(self::LIMIT)
            ->values();

        return $merged;
    }
}
