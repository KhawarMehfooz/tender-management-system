<?php

return [
    'navigation_label' => 'Notification preferences',
    'title' => 'Notification preferences',
    'description' => 'Choose which task notifications you also want to receive by email. In-app notifications are always on.',
    'type_column' => 'Notification',
    'email_column' => 'Email',

    'type' => [
        'task-status-changed' => 'Task status changed',
        'task-comment-added' => 'Task comment added',
        'task-attachment-added' => 'Task attachment added',
        'task-escalated-assignee' => 'Task overdue (assignee)',
        'task-escalated-team-lead' => 'Task overdue (team lead)',
        'task-escalated-administrator' => 'Critical task nearing submission (administrator)',
        'tender-escalated-management' => 'Submission deadline critical (management)',
    ],

    'actions' => [
        'view_task' => 'View task',
        'view_tender' => 'View tender',
    ],

    'task_status_changed' => [
        'title' => 'Task status changed: :task',
        'body' => 'Status changed from :from to :to.',
        'mail_subject' => 'Task status changed: :task',
        'mail_line' => 'The status of task ":task" changed from :from to :to.',
    ],

    'task_comment_added' => [
        'title' => 'New comment on: :task',
        'body' => ':author added a comment.',
        'mail_subject' => 'New comment on task: :task',
        'mail_line' => ':author added a comment to task ":task".',
    ],

    'task_attachment_added' => [
        'title' => 'New attachment on: :task',
        'body' => ':uploader uploaded :filename.',
        'mail_subject' => 'New attachment on task: :task',
        'mail_line' => ':uploader uploaded ":filename" to task ":task".',
    ],

    'task_escalated_assignee' => [
        'title' => 'Task overdue: :task',
        'body' => 'This task is overdue and needs your attention.',
        'mail_subject' => 'Task overdue: :task',
        'mail_line' => 'Task ":task" is now overdue.',
    ],

    'task_escalated_team_lead' => [
        'title' => 'Task overdue for over 24 hours: :task',
        'body' => 'Assigned to :owner. Still overdue after 24 hours.',
        'mail_subject' => 'Task overdue for over 24 hours: :task',
        'mail_line' => 'Task ":task", assigned to :owner, has been overdue for more than 24 hours.',
    ],

    'task_escalated_administrator' => [
        'title' => 'Critical task nearing submission: :task',
        'body' => 'Urgent task on tender :tender is still open with less than 48 hours before submission.',
        'mail_subject' => 'Critical task nearing submission: :task',
        'mail_line' => 'Urgent task ":task" on tender ":tender" is still open with less than 48 hours before the submission deadline.',
    ],

    'tender_escalated_management' => [
        'title' => 'Submission deadline critical: :tender',
        'body' => 'Less than 24 hours before submission, with :count critical item(s) still open.',
        'mail_subject' => 'Submission deadline critical: :tender',
        'mail_line' => 'Tender ":tender" has less than 24 hours before its submission deadline, with :count critical item(s) still open.',
    ],
];
