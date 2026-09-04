<?php

namespace App\Models;

use App\Enums\TeamRole;
use Database\Factories\TenderTeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property string $user_id
 * @property TeamRole $functional_role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_id', 'user_id', 'functional_role'])]
class TenderTeamMember extends Model
{
    /** @use HasFactory<TenderTeamMemberFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'functional_role' => TeamRole::class,
        ];
    }

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
