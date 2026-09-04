<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentDownloadController extends Controller
{
    /**
     * Stream the attachment's file to the browser. `Task::query()` re-applies
     * TaskTenderCategoryScope, so a category-scoped user requesting another category's
     * attachment gets a 404 here rather than a reachable-but-hidden download link — same
     * "no hidden-but-reachable page" rule as tender/task viewing (see [[scopes-models]]).
     */
    public function __invoke(TaskAttachment $taskAttachment): StreamedResponse|Response
    {
        Task::query()->findOrFail($taskAttachment->task_id);

        abort_unless(Storage::disk('local')->exists($taskAttachment->file_path), 404);

        return Storage::disk('local')->download(
            $taskAttachment->file_path,
            $taskAttachment->original_filename,
        );
    }
}
