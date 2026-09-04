<?php

namespace App\Models;

use Database\Factories\TenderLessonsLearnedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property string $went_well
 * @property string $differently_next_time
 * @property string $process_changes
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'went_well', 'differently_next_time', 'process_changes', 'created_by',
])]
class TenderLessonsLearned extends Model
{
    /** @use HasFactory<TenderLessonsLearnedFactory> */
    use HasFactory, HasUuids;

    protected $table = 'tender_lessons_learned';

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
}
