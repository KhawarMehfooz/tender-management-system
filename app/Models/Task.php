<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\InvalidTaskStatusTransitionException;
use App\Models\Scopes\TaskTenderCategoryScope;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $tender_id
 * @property string $title
 * @property string|null $description
 * @property string $owner_id
 * @property string $creator_id
 * @property string|null $reviewer_id
 * @property TaskPriority $priority
 * @property TaskStatus $status
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completion_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'title', 'description', 'owner_id', 'creator_id', 'reviewer_id', 'priority',
    'status', 'start_date', 'due_date', 'completion_date',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope(new TaskTenderCategoryScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completion_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tender, $this>
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_participants')->withTimestamps();
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<TaskStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(TaskStatusChange::class)->latest('changed_at');
    }

    /**
     * @return HasMany<TaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    /**
     * Whether the given user is one of the task's assigned people (owner, creator, reviewer,
     * or participant) — the set allowed to add attachments, distinct from
     * TaskForm::canManageTask()'s narrower management-only set for owner/reviewer/participant
     * assignment itself.
     */
    public function isLinkedTo(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->creator_id === $user->id
            || $this->reviewer_id === $user->id
            || $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * A task is overdue if its due date has passed and it hasn't been completed. This is
     * deliberately computed, not stored: storing it would need a background job to keep in
     * sync with wall-clock time (see [[resources-tenders]]'s archived/invalid axis for the
     * same "separate from the transition map" reasoning).
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== TaskStatus::DONE;
    }

    /**
     * Move the task to a new status, enforcing the allowed-transitions map in TaskStatus and
     * recording an audit entry (who, when, from/to), mirroring Tender::changeStatusTo(). Also
     * stamps completion_date when the task reaches DONE.
     */
    public function changeStatusTo(TaskStatus $newStatus, User $actor, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidTaskStatusTransitionException::make($this->status, $newStatus);
        }

        DB::transaction(function () use ($newStatus, $actor, $reason): void {
            $fromStatus = $this->status;
            $changedAt = now();

            $this->update([
                'status' => $newStatus,
                'completion_date' => $newStatus === TaskStatus::DONE ? $changedAt : null,
            ]);

            $this->statusChanges()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $actor->id,
                'reason' => $reason,
                'changed_at' => $changedAt,
            ]);
        });
    }
}
