<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\TaskStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $task_id
 * @property TaskStatus $from_status
 * @property TaskStatus $to_status
 * @property string $changed_by
 * @property string|null $reason
 * @property Carbon $changed_at
 */
#[Fillable(['task_id', 'from_status', 'to_status', 'changed_by', 'reason', 'changed_at'])]
class TaskStatusChange extends Model
{
    /** @use HasFactory<TaskStatusChangeFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
