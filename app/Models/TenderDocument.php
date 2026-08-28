<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Database\Factories\TenderDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property DocumentCategory $category
 * @property string $title
 * @property string $created_by
 * @property Carbon|null $locked_at
 * @property string|null $locked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_id', 'category', 'title', 'created_by'])]
class TenderDocument extends Model
{
    /** @use HasFactory<TenderDocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'locked_at' => 'datetime',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * @return HasMany<TenderDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TenderDocumentVersion::class)->orderByDesc('version_number');
    }

    /**
     * The latest uploaded version — deliberately an explicit orderBy()+limit() HasOne rather
     * than ofMany(), which can't run MAX() against this app's UUID PKs (see [[models]]).
     *
     * @return HasOne<TenderDocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(TenderDocumentVersion::class)->orderByDesc('version_number')->limit(1);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Lock the document against further versions/deletion — called from
     * Tender::changeStatusTo() when the tender reaches SUBMISSION for every document that
     * already exists at that moment. forceFill since locked_at/locked_by are excluded from
     * #[Fillable(...)] (same pattern as Tender's archive columns, see [[tenders]]).
     */
    public function lock(User $actor): void
    {
        $this->forceFill([
            'locked_at' => now(),
            'locked_by' => $actor->id,
        ])->save();
    }
}
