<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationType: string implements HasLabel
{
    case TASK_STATUS_CHANGED = 'task-status-changed';
    case TASK_COMMENT_ADDED = 'task-comment-added';
    case TASK_ATTACHMENT_ADDED = 'task-attachment-added';
    case TASK_ESCALATED_ASSIGNEE = 'task-escalated-assignee';
    case TASK_ESCALATED_TEAM_LEAD = 'task-escalated-team-lead';
    case TASK_ESCALATED_ADMINISTRATOR = 'task-escalated-administrator';
    case TENDER_ESCALATED_MANAGEMENT = 'tender-escalated-management';

    public function getLabel(): string
    {
        return __('notifications.type.'.$this->value);
    }
}
