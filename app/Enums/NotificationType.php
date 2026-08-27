<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationType: string implements HasLabel
{
    case TASK_STATUS_CHANGED = 'task-status-changed';
    case TASK_COMMENT_ADDED = 'task-comment-added';
    case TASK_ATTACHMENT_ADDED = 'task-attachment-added';

    public function getLabel(): string
    {
        return __('notifications.type.'.$this->value);
    }
}
