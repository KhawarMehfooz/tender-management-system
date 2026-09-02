<?php

namespace App\Models;

use Database\Factories\TenderSiteVisitFactory;
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
 * @property Carbon $visit_date
 * @property string $attendees
 * @property string|null $contact_person
 * @property string|null $access_routes
 * @property string|null $parking
 * @property string|null $areas
 * @property string|null $risks
 * @property string|null $technical_particularities
 * @property string|null $staffing_requirement
 * @property string|null $competitors_spotted
 * @property string|null $open_questions
 * @property string|null $notes
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'visit_date', 'attendees', 'contact_person', 'access_routes', 'parking',
    'areas', 'risks', 'technical_particularities', 'staffing_requirement',
    'competitors_spotted', 'open_questions', 'notes', 'created_by',
])]
class TenderSiteVisit extends Model
{
    /** @use HasFactory<TenderSiteVisitFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
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
     * @return HasMany<TenderSiteVisitPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TenderSiteVisitPhoto::class);
    }
}
