---
paths:
  - 'database/seeders/**'
  - database/seeders/DemoDataSeeder.php
---

# Database Seeders

## DatabaseSeeder's WithoutModelEvents breaks Spatie permission seeding — fixed
`DatabaseSeeder` uses `use WithoutModelEvents;`, which mutes model events for its entire `run()` — including everything called via `$this->call()`. Spatie's permission package invalidates its cache via a `Permission`/`Role` model-saved event, so with events muted, `RolesAndPermissionsSeeder`'s `Permission::findOrCreate()` inserts rows but the cached (stale/empty) permission collection never refreshes, and the immediately-following `Role::syncPermissions()` throws `PermissionDoesNotExist`. Reproduced reliably on a clean DB via `php artisan migrate:fresh --seed`. Fixed by adding an explicit `app(PermissionRegistrar::class)->forgetCachedPermissions();` call in `RolesAndPermissionsSeeder::run()`, right after the `Permission::findOrCreate()` loop and before `Role::syncPermissions()` — scoped to the one seeder that needs it, rather than dropping `WithoutModelEvents` from `DatabaseSeeder` (which still benefits the bulk `CpvCodeSeeder`/`NutsCodeSeeder` imports). If a similar seeder ever creates permissions/roles and then immediately checks/syncs them within the same `WithoutModelEvents`-wrapped run, it needs the same explicit cache-forget call.

## DatabaseSeeder's WithoutModelEvents mutes more than the Spatie cache — Tender internal_id too
Any seeder called from DatabaseSeeder::run() (wrapped in WithoutModelEvents, which unsets the model event dispatcher for the whole nested $this->call() chain) that creates Tender rows will get `internal_id` NOT NULL violations — Tender::booted()'s `static::creating` hook that generates it never fires. Fix: at the top of the seeder's run(), call `Model::setEventDispatcher(app('events'));` to restore the dispatcher for that seeder's duration (see DemoDataSeeder::run() for the reference fix — same class of issue as the Spatie permission-cache trap already documented for RolesAndPermissionsSeeder).

## Create/complete tasks before walking a tender's status forward
Tender::changeStatusTo() gates the quality->submission transition on Tender::tasksComplete() (see [[tenders]]). Task creation in createTender() must happen BEFORE advanceTender()'s status walk, not after — otherwise the walk hits SUBMISSION while the tender has zero tasks yet (tasksComplete() is vacuously true) and the gate silently no-ops, leaving seeded tenders sitting at submission/follow-up/won/lost with open tasks once they're created afterward (confirmed via a live query: all 12 such tenders had open tasks before this was fixed). For the 4 statuses only reachable via SUBMISSION (submission/follow-up/won/lost), every created task must be forced to `done` (see REQUIRES_COMPLETE_TASKS and createTask()'s $forceDone param) — the random/overdue task-status logic only applies to earlier-phase tenders.
