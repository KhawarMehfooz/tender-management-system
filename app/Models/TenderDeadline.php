<?php

namespace App\Models;

use App\Enums\DeadlineType;
use App\Enums\EscalationLevel;
use App\Models\Scopes\TenderDeadlineCategoryScope;
use Database\Factories\TenderDeadlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property DeadlineType $type
 * @property Carbon $due_at
 * @property EscalationLevel|null $escalation_level
 * @property Carbon|null $last_escalated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_id', 'type', 'due_at'])]
class TenderDeadline extends Model
{
    /** @use HasFactory<TenderDeadlineFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenderDeadlineCategoryScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeadlineType::class,
            'due_at' => 'datetime',
            'escalation_level' => EscalationLevel::class,
            'last_escalated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tender, $this>
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
