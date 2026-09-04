<?php

namespace App\Models;

use App\Enums\ConceptBlockCategory;
use Database\Factories\ConceptBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ConceptBlockCategory $category
 * @property string $title
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pivot|null $pivot Only set when loaded through Tender::conceptBlocks().
 */
#[Fillable(['category', 'title', 'created_by'])]
class ConceptBlock extends Model
{
    /** @use HasFactory<ConceptBlockFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ConceptBlockCategory::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ConceptBlockVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ConceptBlockVersion::class)->orderByDesc('version_number');
    }

    /**
     * The latest edit — deliberately an explicit orderBy()+limit() HasOne rather than
     * ofMany(), which can't run MAX() against this app's UUID PKs (see [[models]]).
     *
     * @return HasOne<ConceptBlockVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(ConceptBlockVersion::class)->orderByDesc('version_number')->limit(1);
    }

    /**
     * @return BelongsToMany<Tender, $this>
     */
    public function tenders(): BelongsToMany
    {
        return $this->belongsToMany(Tender::class, 'tender_concept_block')
            ->withPivot('concept_block_version_id')
            ->withTimestamps();
    }
}
