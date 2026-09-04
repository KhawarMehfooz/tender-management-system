---
paths:
  - 'app/Models/Task.php,database/factories/TaskFactory.php,database/seeders/**'
---

# Factories Seeders

## Task::changeStatusTo() right after create() needs an explicit 'status', not the DB default
tasks.status has a DB-level default ('open'), and TaskFactory deliberately doesn't set it — but Eloquent's create() doesn't re-fetch DB defaults into the in-memory model, so $task->status is null immediately after Task::factory()->create() unless the DB row is re-read. Calling changeStatusTo() on that fresh instance throws "Call to a member function canTransitionTo() on null". Fix: pass 'status' => TaskStatus::OPEN explicitly in the create() call (mirrors TenderFactory explicitly setting 'status' => TenderStatus::INTAKE for the same reason), or ->refresh() the model first.
