<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Internal per-category-per-year counter backing Tender::internal_id generation.
 * Not user-facing, no Filament resource.
 *
 * @property string $id
 * @property string $service_category_id
 * @property int $year
 * @property int $last_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_category_id', 'year', 'last_sequence'])]
class TenderNumberSequence extends Model
{
    use HasUuids;
}
