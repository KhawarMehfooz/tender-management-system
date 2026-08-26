<?php

namespace App\Models;

use App\Enums\TenderStatus;
use Database\Factories\TenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @property string $id
 * @property string $internal_id
 * @property string $title
 * @property string|null $procurement_number
 * @property string $contracting_authority
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
 * @property string|null $extension_options
 * @property Carbon $submission_deadline
 * @property Carbon|null $bidder_question_deadline
 * @property Carbon|null $site_visit_date
 * @property int|null $bid_validity_days
 * @property Carbon|null $publication_date
 * @property string $source_id
 * @property string|null $cpv_code_id
 * @property string|null $portal_link
 * @property string|null $notes
 * @property TenderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'procurement_number',
    'contracting_authority',
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
    'submission_deadline',
    'bidder_question_deadline',
    'site_visit_date',
    'bid_validity_days',
    'publication_date',
    'source_id',
    'cpv_code_id',
    'portal_link',
    'notes',
    'status',
])]
class Tender extends Model
{
    /** @use HasFactory<TenderFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
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
            'submission_deadline' => 'datetime',
            'bidder_question_deadline' => 'datetime',
            'site_visit_date' => 'datetime',
            'publication_date' => 'date',
            'status' => TenderStatus::class,
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
}
