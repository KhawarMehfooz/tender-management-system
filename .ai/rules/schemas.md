---
paths:
  - app/Filament/Resources/Tasks/Schemas/TaskInfolist.php
  - 'app/Filament/Resources/Tasks/Schemas/*.php'
---

# Schemas

## TaskInfolist had no Attachments section at all
Unlike Comments, `TaskInfolist` never had an Attachments section — attachments were only ever reachable via `AttachmentsRelationManager`'s own tab on the standalone TaskResource pages, so they were invisible anywhere the infolist is used standalone (including the `TasksRelationManager` View action fixed in [[tenders-relation-managers]]). Added a matching "Attachments" `Section` (RepeatableEntry over `attachments`: filename as a download link via `route('task-attachments.download', $record)`, size via `Number::fileSize()`, uploader, uploaded-at), gated by the same `canSeeLinkedContent()` check now shared with Comments (renamed from `canSeeComments()`). Regression test: `TaskResourceTest.php`'s "tasks relation manager on a tender" group, "shows a task's attachments in the view action modal".

## Checklist items can be marked done on the edit form; infolist shows description before status
`TaskChecklistItem.is_done` used to have no UI to set it at all (only readable via the infolist icon). `TaskForm`'s `checklistItems` Repeater now includes a `Checkbox::make('is_done')` alongside `description` (2-col description + 1-col checkbox, `->columns(3)`) so it's actually settable from the app.

`TaskInfolist`'s checklist `RepeatableEntry` schema order is `description` then `is_done` (description reads first, done-status icon second) — don't flip this back to is_done-first.

Test-writing trap: filling `checklistItems` via `fillForm()` on `EditTask` does NOT update existing rows by id — Filament's Repeater relationship save only matches items by their in-state repeater key, and `fillForm` replaces state wholesale, so it looks like an update but actually deletes+recreates. To toggle a field on an already-persisted item in a test, mount the Livewire component first, find the item's repeater array key via `$component->get('data.checklistItems')` (search by a stable field like description), then `$component->set("data.checklistItems.{$key}.is_done", true)` before `->call('save')`. See `TaskResourceTest.php`, "marks a checklist item done through the edit form repeater".
