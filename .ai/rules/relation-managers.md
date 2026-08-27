---
paths:
  - 'app/Filament/Resources/Tasks/RelationManagers/*'
---

# Relation Managers

## Task child-entity relation managers use isLinkedTo + canManageTask gating
Both AttachmentsRelationManager and CommentsRelationManager gate "create" on `Task::isLinkedTo($user) || TaskForm::canManageTask()` — linked users (owner/creator/reviewer/participant) plus managers. "Delete" is author-or-manager. The relation manager must specify `'pageClass' => EditTask::class` in test calls because Filament v4's read-only-relation-managers-on-ViewRecord-pages default hides all mutating actions on ViewTask.

## Docs
Comments and attachments are documented for end users in `docs/06-collaboration.md`. If access
rules or behavior here change, update that page too — see [[docs]].
