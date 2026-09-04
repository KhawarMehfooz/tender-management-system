<?php

namespace App\Models;

use App\Enums\AbsenceType;
use Carbon\CarbonInterface;
use Database\Factories\UserAbsenceFactory;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property AbsenceType $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $notes
 * @property string|null $cover_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'type', 'starts_at', 'ends_at', 'notes', 'cover_user_id'])]
class UserAbsence extends Model implements Eventable
{
    /** @use HasFactory<UserAbsenceFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cover_user_id');
    }

    /**
     * Whether this absence covers the given moment (inclusive on both ends — a one-day absence
     * still covers any time during that day). Accepts CarbonInterface, not the app's usual
     * Illuminate\Support\Carbon, since AppServiceProvider configures now()/today() etc. to
     * return Carbon\CarbonImmutable app-wide.
     */
    public function covers(CarbonInterface $moment): bool
    {
        return $moment->betweenIncluded(
            $this->starts_at->copy()->startOfDay(),
            $this->ends_at->copy()->endOfDay(),
        );
    }

    public function toCalendarEvent(): CalendarEvent
    {
        return CalendarEvent::make($this)
            ->title("{$this->user->name}: {$this->type->getLabel()}")
            ->allDay()
            ->start($this->starts_at)
            // FullCalendar treats an all-day event's end date as exclusive, so the range must
            // extend one day past ends_at for the last day to actually render.
            ->end($this->ends_at->copy()->addDay())
            ->extendedProps([
                'user_id' => $this->user_id,
            ]);
    }
}
