<?php

namespace App\Models;

use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $region
 * @property string|null $service_areas
 * @property string|null $known_clients
 * @property string|null $strengths
 * @property string|null $weaknesses
 * @property string|null $market_segments
 * @property string|null $internal_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'region',
    'service_areas',
    'known_clients',
    'strengths',
    'weaknesses',
    'market_segments',
    'internal_notes',
])]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<CompetitorPriceEntry, $this>
     */
    public function priceEntries(): HasMany
    {
        return $this->hasMany(CompetitorPriceEntry::class)->orderByDesc('observed_on');
    }
}
