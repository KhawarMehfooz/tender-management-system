<?php

namespace App\Models;

use App\Enums\CostDriverFieldType;
use Database\Factories\ServiceCategoryCostDriverFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $service_category_id
 * @property string $field_key
 * @property string $label
 * @property CostDriverFieldType $type
 * @property string|null $unit
 * @property bool $required
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_category_id', 'field_key', 'label', 'type', 'unit', 'required', 'order'])]
class ServiceCategoryCostDriverField extends Model
{
    /** @use HasFactory<ServiceCategoryCostDriverFieldFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CostDriverFieldType::class,
            'required' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }
}
