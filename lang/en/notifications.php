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
    ],

    'actions' => [
        'view_task' => 'View task',
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
];
