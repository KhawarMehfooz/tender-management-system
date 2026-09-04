<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Enums\TaskStatus;
use App\Enums\TenderStatus;
use App\Exceptions\InvalidTenderStatusTransitionException;
use App\Exceptions\TenderCalculationNotApprovedException;
use App\Models\Scopes\ServiceCategoryScope;
use Database\Factories\TenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @property string $id
 * @property string $internal_id
 * @property string $title
 * @property string|null $procurement_number
 * @property string $contracting_authority
 * @property string|null $client_id
 * @property string|null $procurement_office
 * @property string|null $contact_person
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $city
 * @property string|null $nuts_code_id
 * @property string $service_category_id
 * @property string $sector_id
 * @property string $procurement_procedure_id
 * @property float|null $estimated_contract_volume
 * @property bool $estimated_contract_volume_unknown
 * @property string|null $contract_term
 * @property Carbon|null $contract_start_date
 * @property Carbon|null $contract_end_date
 * @property Carbon|null $reminder_12_months_sent_at
 * @property Carbon|null $reminder_9_months_sent_at
 * @property Carbon|null $reminder_6_months_sent_at
 * @property string|null $extension_options
 * @property int|null $bid_validity_days
 * @property Carbon|null $publication_date
 * @property string $source_id
 * @property string|null $cpv_code_id
 * @property string|null $portal_link
 * @property string|null $notes
 * @property string $owner_id
 * @property TenderStatus $status
 * @property bool $is_archived
 * @property Carbon|null $archived_at
 * @property string|null $archived_by
 * @property string|null $invalidity_reason
 * @property Carbon|null $invalidated_at
 * @property string|null $invalidated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'procurement_number',
    'contracting_authority',
    'client_id',
    'procurement_office',
    'contact_person',
    'contact_email',
    'contact_phone',
    'city',
    'nuts_code_id',
    'service_category_id',
    'sector_id',
    'procurement_procedure_id',
    'estimated_contract_volume',
    'estimated_contract_volume_unknown',
    'contract_term',
    'contract_start_date',
    'contract_end_date',
    'extension_options',
    'bid_validity_days',
    'publication_date',
    'source_id',
    'cpv_code_id',
    'portal_link',
    'notes',
    'owner_id',
    'status',
    // is_archived/archived_at/archived_by/invalidity_reason/invalidated_at/invalidated_by are
    // intentionally excluded: they're only ever written via archive()/unarchive()/markInvalid()/
    // clearInvalidFlag() below (using forceFill), never through form mass assignment.
    // reminder_12_months_sent_at/reminder_9_months_sent_at/reminder_6_months_sent_at are
    // likewise excluded — only CheckClientContractRenewals writes them, via forceFill().
])]
class Tender extends Model
{
    /** @use HasFactory<TenderFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope(new ServiceCategoryScope);

        static::creating(function (Tender $tender): void {
            if ($tender->internal_id) {
                return;
            }

            $tender->internal_id = static::generateInternalId($tender->service_category_id);
        });
    }

    protected static function generateInternalId(string $serviceCategoryId): string
    {
        $category = ServiceCategory::query()->findOrFail($serviceCategoryId);

        if (! $category->code) {
            throw new RuntimeException(
                "Service category \"{$category->name}\" needs a code before tenders can be created under it."
            );
        }

        $year = (int) now()->format('Y');

        $sequence = DB::transaction(function () use ($category, $year): int {
            $row = TenderNumberSequence::query()
                ->where('service_category_id', $category->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = TenderNumberSequence::create([
                    'service_category_id' => $category->id,
                    'year' => $year,
                    'last_sequence' => 0,
                ]);
            }

            $row->increment('last_sequence');

            return $row->last_sequence;
        });

        return sprintf('%s-%d-%04d', $category->code, $year, $sequence);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_contract_volume' => 'decimal:2',
            'estimated_contract_volume_unknown' => 'boolean',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'reminder_12_months_sent_at' => 'datetime',
            'reminder_9_months_sent_at' => 'datetime',
            'reminder_6_months_sent_at' => 'datetime',
            'publication_date' => 'date',
            'status' => TenderStatus::class,
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<ProcurementProcedure, $this>
     */
    public function procurementProcedure(): BelongsTo
    {
        return $this->belongsTo(ProcurementProcedure::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<NutsCode, $this>
     */
    public function nutsCode(): BelongsTo
    {
        return $this->belongsTo(NutsCode::class);
    }

    /**
     * @return BelongsTo<CpvCode, $this>
     */
    public function cpvCode(): BelongsTo
    {
        return $this->belongsTo(CpvCode::class);
    }

    /**
     * @return HasMany<TenderStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(TenderStatusChange::class)->latest('changed_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<TenderTeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TenderTeamMember::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TenderDeadline, $this>
     */
    public function deadlines(): HasMany
    {
        return $this->hasMany(TenderDeadline::class);
    }

    /**
     * @return HasMany<TenderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TenderDocument::class);
    }

    /**
     * @return HasMany<TenderCommunication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(TenderCommunication::class)->orderByDesc('occurred_at');
    }

    /**
     * @return HasMany<TenderSiteVisit, $this>
     */
    public function siteVisits(): HasMany
    {
        return $this->hasMany(TenderSiteVisit::class)->orderByDesc('visit_date');
    }

    /**
     * @return HasOne<TenderSubmission, $this>
     */
    public function submission(): HasOne
    {
        return $this->hasOne(TenderSubmission::class);
    }

    /**
     * @return HasOne<TenderFollowUp, $this>
     */
    public function followUp(): HasOne
    {
        return $this->hasOne(TenderFollowUp::class);
    }

    /**
     * @return HasMany<TenderDocumentRequest, $this>
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(TenderDocumentRequest::class)->orderByDesc('created_at');
    }

    /**
     * @return HasOne<TenderResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(TenderResult::class);
    }

    /**
     * @return HasOne<TenderLessonsLearned, $this>
     */
    public function lessonsLearned(): HasOne
    {
        return $this->hasOne(TenderLessonsLearned::class);
    }

    /**
     * @return HasMany<TenderCalculation, $this>
     */
    public function calculations(): HasMany
    {
        return $this->hasMany(TenderCalculation::class)->orderByDesc('version_number');
    }

    /**
     * Not ofMany() — Postgres can't run MAX() against this app's UUID PKs ([[models]]).
     *
     * @return HasOne<TenderCalculation, $this>
     */
    public function currentCalculation(): HasOne
    {
        return $this->hasOne(TenderCalculation::class)->orderByDesc('version_number')->limit(1);
    }

    /**
     * @return HasOne<TenderParticipationScore, $this>
     */
    public function participationScore(): HasOne
    {
        return $this->hasOne(TenderParticipationScore::class);
    }

    /**
     * @return HasMany<TenderBidDecision, $this>
     */
    public function bidDecisions(): HasMany
    {
        return $this->hasMany(TenderBidDecision::class)->orderByDesc('decided_at');
    }

    /**
     * @return HasMany<TenderCompetitor, $this>
     */
    public function tenderCompetitors(): HasMany
    {
        return $this->hasMany(TenderCompetitor::class);
    }

    /**
     * Not ofMany() — Postgres can't run MAX() against this app's UUID PKs ([[models]]).
     *
     * @return HasOne<TenderBidDecision, $this>
     */
    public function currentBidDecision(): HasOne
    {
        return $this->hasOne(TenderBidDecision::class)->orderByDesc('decided_at')->limit(1);
    }

    /**
     * Whether the given user may upload/manage this tender's documents by virtue of being
     * linked to the tender itself — the owner or any tender_team_members row — mirroring
     * Task::isLinkedTo(). Distinct from TenderForm::canManageTeam()'s broader manager set,
     * which is checked separately wherever this alone isn't enough (see [[documents]]).
     */
    public function linkedToDocuments(User $user): bool
    {
        return $this->owner_id === $user->id || $this->teamMembers()->where('user_id', $user->id)->exists();
    }

    /**
     * References from the global library actually used on this bid — attach/detach only,
     * creation stays on ReferenceResource (see [[milestones]]).
     *
     * @return BelongsToMany<Reference, $this>
     */
    public function bidReferences(): BelongsToMany
    {
        return $this->belongsToMany(Reference::class, 'tender_bid_reference', 'tender_id', 'bid_reference_id')->withTimestamps();
    }

    /**
     * Certificates from the global library actually used on this bid — attach/detach only,
     * creation stays on CertificateResource (see [[milestones]]).
     *
     * @return BelongsToMany<Certificate, $this>
     */
    public function certificates(): BelongsToMany
    {
        return $this->belongsToMany(Certificate::class, 'tender_certificate')->withTimestamps();
    }

    /**
     * Concept blocks from the global library actually used on this bid. The pivot pins the
     * specific ConceptBlockVersion used at attach time (concept_block_version_id) — editing the
     * block afterward must not silently change what a past bid is recorded as having submitted.
     *
     * @return BelongsToMany<ConceptBlock, $this>
     */
    public function conceptBlocks(): BelongsToMany
    {
        return $this->belongsToMany(ConceptBlock::class, 'tender_concept_block')
            ->withPivot('concept_block_version_id')
            ->withTimestamps();
    }

    /**
     * The tender's canonical deadline of a given type, if one has been recorded — the latest
     * by due date when more than one row of that type exists (e.g. a rescheduled deadline).
     */
    public function latestDeadlineOfType(DeadlineType $type): ?TenderDeadline
    {
        return $this->deadlines()->where('type', $type)->latest('due_at')->first();
    }

    /**
     * The tender's canonical submission deadline, which must always be visible.
     */
    public function submissionDeadline(): ?TenderDeadline
    {
        return $this->latestDeadlineOfType(DeadlineType::SUBMISSION);
    }

    /**
     * Create/update/remove the single deadline row of a given type, keyed by type — used by
     * the tender wizard's dedicated deadline fields (submission/bidder-questions/site-visit),
     * each of which represents one canonical value rather than an open list. Null removes the
     * row entirely (the field was cleared).
     */
    public function upsertDeadline(DeadlineType $type, \DateTimeInterface|string|null $dueAt): void
    {
        if ($dueAt === null) {
            $this->deadlines()->where('type', $type)->delete();

            return;
        }

        $this->deadlines()->updateOrCreate(['type' => $type], ['due_at' => $dueAt]);
    }

    /**
     * Keep the derived BID_VALIDITY deadline row (submission due date + bid_validity_days) in
     * sync with its two inputs, via upsertDeadline() — removed entirely once either input
     * becomes unknown.
     */
    public function syncBidValidityDeadline(): void
    {
        $submissionDeadline = $this->submissionDeadline();

        $dueAt = ($submissionDeadline && $this->bid_validity_days !== null)
            ? $submissionDeadline->due_at->copy()->addDays($this->bid_validity_days)
            : null;

        $this->upsertDeadline(DeadlineType::BID_VALIDITY, $dueAt);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    /**
     * Move the tender to a new status, enforcing the allowed-transitions map in
     * TenderStatus and recording an audit entry (who, when, from/to).
     */
    public function changeStatusTo(TenderStatus $newStatus, User $actor, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidTenderStatusTransitionException::make($this->status, $newStatus);
        }

        if ($newStatus === TenderStatus::SUBMISSION && ! ($this->currentCalculation()->first()?->isFullyApproved() ?? false)) {
            throw TenderCalculationNotApprovedException::make($this);
        }

        DB::transaction(function () use ($newStatus, $actor, $reason): void {
            $fromStatus = $this->status;
            $changedAt = now();

            $this->update(['status' => $newStatus]);

            $this->statusChanges()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $actor->id,
                'reason' => $reason,
                'changed_at' => $changedAt,
            ]);

            if ($newStatus === TenderStatus::SUBMISSION) {
                $this->lockDocuments($actor);
            }
        });
    }

    /**
     * Lock every document that already exists on the tender at the moment it reaches
     * SUBMISSION — once a tender is submitted, its final submission version is
     * locked/immutable. Documents created afterwards (e.g. Result, Post-analysis in later
     * milestones) are untouched — they're new documents, not edits to already-submitted ones.
     * Already-locked documents are left alone (no re-stamping locked_at/locked_by).
     */
    protected function lockDocuments(User $actor): void
    {
        $this->documents()->whereNull('locked_at')->each(fn (TenderDocument $document) => $document->lock($actor));
    }

    /**
     * Whether every task on this tender is done — gates the SUBMISSION transition in
     * changeStatusTo() per the "final submission is gated" rule.
     */
    public function tasksComplete(): bool
    {
        return ! $this->tasks()->where('status', '!=', TaskStatus::DONE->value)->exists();
    }

    public function isInvalid(): bool
    {
        return $this->invalidated_at !== null;
    }

    /**
     * Archive the tender. Tenders are never hard-deleted, only archived or
     * flagged invalid. Distinct from TenderStatus: a tender can be archived
     * from any status, including a terminal one like `won`.
     */
    public function archive(User $actor): void
    {
        $this->forceFill([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $actor->id,
        ])->save();
    }

    public function unarchive(): void
    {
        $this->forceFill([
            'is_archived' => false,
            'archived_at' => null,
            'archived_by' => null,
        ])->save();
    }

    /**
     * Flag the tender invalid — an alternative to hard-delete for
     * junk/mistaken entries, preserving the record rather than removing it.
     */
    public function markInvalid(User $actor, string $reason): void
    {
        $this->forceFill([
            'invalidity_reason' => $reason,
            'invalidated_at' => now(),
            'invalidated_by' => $actor->id,
        ])->save();
    }

    public function clearInvalidFlag(): void
    {
        $this->forceFill([
            'invalidity_reason' => null,
            'invalidated_at' => null,
            'invalidated_by' => null,
        ])->save();
    }

    /**
     * Permanently remove the tender. This is an admin-only escape hatch for
     * true junk entries — every call must be logged with
     * who/when/why before the row disappears, via a TenderHardDeletion
     * snapshot (which carries no FK back to `tenders`, since the row it
     * describes won't exist once this method returns).
     */
    public function hardDelete(User $actor, string $reason): void
    {
        DB::transaction(function () use ($actor, $reason): void {
            TenderHardDeletion::create([
                'tender_id' => $this->id,
                'internal_id' => $this->internal_id,
                'title' => $this->title,
                'deleted_by' => $actor->id,
                'reason' => $reason,
                'deleted_at' => now(),
            ]);

            $this->delete();
        });
    }
}
