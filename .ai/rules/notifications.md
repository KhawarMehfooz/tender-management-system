---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Notification classes: dual-channel pattern + no assignment hook yet
Each Notification class implements ShouldQueue (tms-queue container already runs queue:work against QUEUE_CONNECTION=database), gates the mail channel via User::wantsEmailFor(NotificationType) in via() (database channel always included), and builds toDatabase() via Filament\Notifications\Notification::make()->getDatabaseMessage() so it renders in the panel's database-notifications bell (enabled via ->databaseNotifications() on AdminPanelProvider). Recipients come from Task::linkedUsers() (owner+creator+reviewer+participants), always excluding the acting user via ->reject(). Dispatch only at points with one clean hook — Task::changeStatusTo() and CreateAction::after() on Comments/AttachmentsRelationManager — never inline in scattered Filament mutate hooks. Task assignment notifications are NOT wired: owner/reviewer/participants are set across TaskForm/CreateTask/EditTask with no single trigger point, and this codebase has no Observer pattern to introduce one. Wire that only once a real hook exists.

## Docs
The notification centre and email preferences are documented in `docs/07-notifications.md`
(deliberately silent on the not-yet-wired assignment notification gap above — it only
describes current behavior). If a new notification type ships or the trigger/recipient rules
change, update that page too — see [[docs]].
