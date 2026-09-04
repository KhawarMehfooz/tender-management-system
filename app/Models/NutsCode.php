<?php

namespace App\Models;

use Database\Factories\NutsCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $label
 * @property int $level
 * @property string|null $parent_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'label', 'level', 'parent_id', 'active'])]
class NutsCode extends Model
{
    /** @use HasFactory<NutsCodeFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<NutsCode, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NutsCode::class, 'parent_id');
    }

    /**
     * @return HasMany<NutsCode, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(NutsCode::class, 'parent_id');
    }
}
