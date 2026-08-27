---
paths:
  - 'app/Models/Task.php,database/migrations/*task_participants*'
---

# Migrations

## KNOWN BUG: task_participants.id (uuid) is never populated by attach()/sync()
Task::participants() is a plain belongsToMany(User::class, 'task_participants')->withTimestamps() with no ->using() pivot class. The task_participants migration gives the pivot table its own uuid primary key ($table->uuid('id')->primary()), but Eloquent's default attach()/sync() insert the pivot row via the query builder directly, bypassing model 'creating' events — so 'id' is never generated and any attach/sync (including via a Filament ->relationship('participants', ...) multi-select field, which saves via sync()) throws a NOT NULL constraint violation. Discovered while writing tests for task attachments (2026-08-27); confirm/fix by giving Task::participants() a dedicated Pivot model with HasUuids via ->using(), or dropping the pivot's own id column in favor of a composite key. Not fixed as part of that unrelated task — flag to the user before relying on participant assignment in production.
