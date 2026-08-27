---
paths:
  - app/Filament/Resources/Tasks/Schemas/TaskInfolist.php
---

# Schemas

## TaskInfolist had no Attachments section at all
Unlike Comments, `TaskInfolist` never had an Attachments section — attachments were only ever reachable via `AttachmentsRelationManager`'s own tab on the standalone TaskResource pages, so they were invisible anywhere the infolist is used standalone (including the `TasksRelationManager` View action fixed in [[tenders-relation-managers]]). Added a matching "Attachments" `Section` (RepeatableEntry over `attachments`: filename as a download link via `route('task-attachments.download', $record)`, size via `Number::fileSize()`, uploader, uploaded-at), gated by the same `canSeeLinkedContent()` check now shared with Comments (renamed from `canSeeComments()`). Regression test: `TaskResourceTest.php`'s "tasks relation manager on a tender" group, "shows a task's attachments in the view action modal".
