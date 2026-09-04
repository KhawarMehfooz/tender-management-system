<?php

namespace App\Models;

use Database\Factories\ConceptBlockVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $concept_block_id
 * @property int $version_number
 * @property string $content
 * @property string $created_by
 * @property Carbon|null $created_at
 */
#[Fillable(['concept_block_id', 'version_number', 'content', 'created_by'])]
class ConceptBlockVersion extends Model
{
    /** @use HasFactory<ConceptBlockVersionFactory> */
    use HasFactory, HasUuids;

    /**
     * No updated_at — versions are immutable by construction, never edited after creation.
     */
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<ConceptBlock, $this>
     */
    public function conceptBlock(): BelongsTo
    {
        return $this->belongsTo(ConceptBlock::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
