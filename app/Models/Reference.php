<?php

namespace App\Models;

use Database\Factories\ReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $client
 * @property string|null $service_category_id
 * @property string|null $sector_id
 * @property string|null $location
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property float|null $contract_volume
 * @property bool $contract_volume_unknown
 * @property int|null $headcount
 * @property string|null $contact_person_name
 * @property string|null $contact_person_email
 * @property string|null $contact_person_phone
 * @property string|null $description
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client',
    'service_category_id',
    'sector_id',
    'location',
    'period_start',
    'period_end',
    'contract_volume',
    'contract_volume_unknown',
    'headcount',
    'contact_person_name',
    'contact_person_email',
    'contact_person_phone',
    'description',
    'created_by',
])]
class Reference extends Model
{
    /** @use HasFactory<ReferenceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'bid_references';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'contract_volume' => 'decimal:2',
            'contract_volume_unknown' => 'boolean',
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
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ReferenceAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReferenceAttachment::class);
    }
}
