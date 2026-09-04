<?php

namespace App\Models;

use Database\Factories\TenderSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property Carbon $submission_date
 * @property string $submission_time
 * @property string $responsible_employee_id
 * @property string $portal
 * @property string $transmission_route
 * @property bool $receipt_confirmed
 * @property Carbon|null $receipt_confirmed_at
 * @property string|null $notes
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'submission_date', 'submission_time', 'responsible_employee_id', 'portal',
    'transmission_route', 'receipt_confirmed', 'receipt_confirmed_at', 'notes', 'created_by',
])]
class TenderSubmission extends Model
{
    /** @use HasFactory<TenderSubmissionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'receipt_confirmed' => 'boolean',
            'receipt_confirmed_at' => 'datetime',
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
    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TenderSubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TenderSubmissionFile::class);
    }
}
