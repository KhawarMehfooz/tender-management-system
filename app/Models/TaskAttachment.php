<?php

namespace App\Models;

use Database\Factories\TaskAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $task_id
 * @property string $uploaded_by
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['task_id', 'uploaded_by', 'file_path', 'original_filename', 'mime_type', 'size'])]
class TaskAttachment extends Model
{
    /** @use HasFactory<TaskAttachmentFactory> */
    use HasFactory, HasUuids;

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
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * A short-lived signed URL rather than a plain route() URL — the download route still
     * requires auth+category-scope on top, but signing means a leaked/cached/shared link
     * (browser history, referrer headers, a screenshot of a URL bar) stops working within
     * minutes rather than staying valid for as long as the viewer's session does.
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'task-attachments.download',
            now()->addMinutes(5),
            ['taskAttachment' => $this],
        );
    }
}
