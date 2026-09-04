<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string|null $service_category_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'service_category_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Whether this user wants email delivery for a given notification type. Absent a stored
     * preference, email defaults to on (opt-out, not opt-in).
     */
    public function wantsEmailFor(NotificationType $type): bool
    {
        $preference = $this->notificationPreferences->firstWhere('notification_type', $type);

        return $preference === null ? true : $preference->email_enabled;
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'user_skills')
            ->withPivot('proficiency_level')
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserAbsence, $this>
     */
    public function absences(): HasMany
    {
        return $this->hasMany(UserAbsence::class);
    }

    /**
     * Absences on which this user is the designated cover, across every other user.
     *
     * @return HasMany<UserAbsence, $this>
     */
    public function coveringAbsences(): HasMany
    {
        return $this->hasMany(UserAbsence::class, 'cover_user_id');
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function participatingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_participants')->withTimestamps();
    }

    /**
     * Tenders this user has actually contributed to — as owner, a tender team member, a task
     * owner, or a task participant — not just formally assigned. Feeds every employee-profile
     * stat below, per idea.md's M11 requirement that these reconcile against actual recorded
     * contribution rather than assignment counts alone.
     *
     * @return EloquentCollection<int, Tender>
     */
    public function tendersHandled(): EloquentCollection
    {
        return Tender::query()
            ->where('owner_id', $this->id)
            ->orWhereHas('teamMembers', fn ($query) => $query->where('user_id', $this->id))
            ->orWhereHas('tasks', fn ($query) => $query->where('owner_id', $this->id))
            ->orWhereHas('tasks.participants', fn ($query) => $query->where('users.id', $this->id))
            ->get();
    }

    /**
     * Distinct tenders handled, grouped by current status value.
     *
     * @return array<string, int>
     */
    public function tendersHandledByStatus(): array
    {
        return $this->tendersHandled()
            ->groupBy(fn (Tender $tender): string => $tender->status->getLabel())
            ->map->count()
            ->all();
    }

    /**
     * Distinct tenders handled, grouped by sector name (an "Unknown" bucket is never needed —
     * sector is required on every tender).
     *
     * @return array<string, int>
     */
    public function sectorExperience(): array
    {
        return $this->tendersHandled()
            ->load('sector')
            ->groupBy(fn (Tender $tender): string => $tender->sector->name)
            ->map->count()
            ->all();
    }

    /**
     * Share of this user's own DONE tasks completed on or before their due date. A task with
     * no due date can't be late, so it counts as on-time. Null when the user has no completed
     * tasks yet, rather than a misleading 0%/100%.
     */
    public function onTimeTaskCompletionRate(): ?float
    {
        $doneTasks = Task::query()
            ->where('owner_id', $this->id)
            ->where('status', TaskStatus::DONE)
            ->get();

        if ($doneTasks->isEmpty()) {
            return null;
        }

        $onTime = $doneTasks->filter(fn (Task $task): bool => $task->due_date === null
            || $task->completion_date === null
            || $task->completion_date->lessThanOrEqualTo($task->due_date))->count();

        return $onTime / $doneTasks->count();
    }

    /**
     * How many times a task owned by this user bounced from IN_REVIEW back to
     * CORRECTION_REQUIRED — the standing "correction loop" signal used across the employee
     * profile and performance score.
     */
    public function correctionLoopCount(): int
    {
        return TaskStatusChange::query()
            ->whereHas('task', fn ($query) => $query->where('owner_id', $this->id))
            ->where('from_status', TaskStatus::IN_REVIEW)
            ->where('to_status', TaskStatus::CORRECTION_REQUIRED)
            ->count();
    }

    /**
     * Average days between a task starting (start_date, falling back to created_at) and its
     * completion, across this user's own DONE tasks. Null when there are none yet.
     */
    public function averageTaskHandlingTimeDays(): ?float
    {
        $doneTasks = Task::query()
            ->where('owner_id', $this->id)
            ->where('status', TaskStatus::DONE)
            ->whereNotNull('completion_date')
            ->get();

        if ($doneTasks->isEmpty()) {
            return null;
        }

        $totalDays = $doneTasks->sum(function (Task $task): int {
            $start = $task->start_date ?? $task->created_at;

            return $start === null ? 0 : (int) abs($task->completion_date->diffInDays($start));
        });

        return $totalDays / $doneTasks->count();
    }

    /**
     * Weights for performanceScore()'s blended formula. idea.md lists the input categories
     * ("on-time delivery, completion rate, quality, reliability, documentation quality,
     * collaboration") but gives no formula — these were confirmed with the user as reasonable
     * defaults, flagged as adjustable. Win rate is deliberately excluded here (see winRate()):
     * idea.md explicitly calls it out as "not the primary driver," so it's surfaced as a
     * separate figure, never blended into this weighted sum.
     *
     * @var array<string, float>
     */
    private const array PERFORMANCE_SCORE_WEIGHTS = [
        'on_time_delivery' => 0.25,
        'task_completion_rate' => 0.20,
        'quality' => 0.20,
        'reliability' => 0.15,
        'documentation_quality' => 0.10,
        'collaboration' => 0.10,
    ];

    /**
     * Weighted 0-100 performance score. Every component below defaults to 0 when the user has
     * no relevant activity yet (e.g. zero done tasks) — unlike the individual profile stats
     * above (which return null for "no data" and are displayed as such), a single blended score
     * has no sensible "no data" state of its own, so it must always resolve to a well-defined
     * float.
     */
    public function performanceScore(): float
    {
        $components = [
            'on_time_delivery' => $this->onTimeTaskCompletionRate() ?? 0.0,
            'task_completion_rate' => $this->taskCompletionRate(),
            'quality' => $this->qualityScore(),
            'reliability' => $this->onTimeTaskStartRate(),
            'documentation_quality' => $this->documentationQualityScore(),
            'collaboration' => $this->collaborationScore(),
        ];

        $weightedSum = collect(self::PERFORMANCE_SCORE_WEIGHTS)
            ->sum(fn (float $weight, string $component): float => $weight * $components[$component]);

        return round($weightedSum * 100, 2);
    }

    /**
     * Share of this user's owned tasks (any status) that reached DONE.
     */
    private function taskCompletionRate(): float
    {
        $ownedTaskCount = Task::query()->where('owner_id', $this->id)->count();

        if ($ownedTaskCount === 0) {
            return 0.0;
        }

        $doneTaskCount = Task::query()->where('owner_id', $this->id)->where('status', TaskStatus::DONE)->count();

        return $doneTaskCount / $ownedTaskCount;
    }

    /**
     * Inverse of the correction-loop rate across this user's DONE tasks (correctionLoopCount()
     * can exceed doneTasks count if a single task bounced more than once, so the rate is capped
     * at 1.0 before inverting).
     */
    private function qualityScore(): float
    {
        $doneTaskCount = Task::query()->where('owner_id', $this->id)->where('status', TaskStatus::DONE)->count();

        if ($doneTaskCount === 0) {
            return 0.0;
        }

        return 1 - min(1.0, $this->correctionLoopCount() / $doneTaskCount);
    }

    /**
     * Share of this user's tasks (with both a start_date and a due_date set) that were started
     * on or before their due date — the "reliability"/"on-time task starts" component.
     */
    private function onTimeTaskStartRate(): float
    {
        $tasks = Task::query()
            ->where('owner_id', $this->id)
            ->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->get();

        if ($tasks->isEmpty()) {
            return 0.0;
        }

        $onTime = $tasks->filter(
            fn (Task $task): bool => $task->start_date->lessThanOrEqualTo($task->due_date)
        )->count();

        return $onTime / $tasks->count();
    }

    /**
     * Share of this user's DONE, documentation-shaped tasks (functional_role EVIDENCE_DOCUMENTS
     * or QUALITY_CONTROL) that completed without ever bouncing from IN_REVIEW to
     * CORRECTION_REQUIRED — the "documentation quality" proxy from the design decision.
     */
    private function documentationQualityScore(): float
    {
        $tasks = Task::query()
            ->where('owner_id', $this->id)
            ->where('status', TaskStatus::DONE)
            ->whereIn('functional_role', [TeamRole::EVIDENCE_DOCUMENTS, TeamRole::QUALITY_CONTROL])
            ->get();

        if ($tasks->isEmpty()) {
            return 0.0;
        }

        $withoutCorrectionLoop = $tasks->filter(fn (Task $task): bool => ! TaskStatusChange::query()
            ->where('task_id', $task->id)
            ->where('from_status', TaskStatus::IN_REVIEW)
            ->where('to_status', TaskStatus::CORRECTION_REQUIRED)
            ->exists())->count();

        return $withoutCorrectionLoop / $tasks->count();
    }

    /**
     * Participant-role task count relative to owner-role task count, capped at 1.0 — a rough
     * proxy for cross-task involvement beyond a user's own owned work, per the design decision.
     */
    private function collaborationScore(): float
    {
        $ownerTaskCount = Task::query()->where('owner_id', $this->id)->count();
        $participantTaskCount = $this->participatingTasks()->count();

        if ($ownerTaskCount === 0) {
            return $participantTaskCount > 0 ? 1.0 : 0.0;
        }

        return min(1.0, $participantTaskCount / $ownerTaskCount);
    }

    /**
     * Win rate across tenders this user contributed to (per tendersHandled()) that reached a
     * decided outcome (WON or LOST) — shown as a separate, secondary figure alongside
     * performanceScore(), never blended into it, per idea.md's explicit "not the primary driver"
     * instruction. Null when the user has no decided tenders yet.
     */
    public function winRate(): ?float
    {
        $decidedTenders = $this->tendersHandled()
            ->whereIn('status', [TenderStatus::WON, TenderStatus::LOST]);

        if ($decidedTenders->isEmpty()) {
            return null;
        }

        return $decidedTenders->where('status', TenderStatus::WON)->count() / $decidedTenders->count();
    }
}
