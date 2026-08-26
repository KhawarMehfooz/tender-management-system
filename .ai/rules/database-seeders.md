---
paths:
  - 'database/seeders/**'
---

# Database Seeders

## DatabaseSeeder's WithoutModelEvents breaks Spatie permission seeding
`DatabaseSeeder` uses `use WithoutModelEvents;`, which mutes model events for its entire `run()` — including everything called via `$this->call()`. Spatie's permission package invalidates its cache via a `Permission`/`Role` model-saved event, so with events muted, `RolesAndPermissionsSeeder`'s `Permission::findOrCreate()` inserts rows but the cached (stale/empty) permission collection never refreshes, and the immediately-following `Role::syncPermissions()` throws `PermissionDoesNotExist`. Reproduces reliably on a clean DB via `php artisan migrate:fresh --seed` or `php artisan db:seed`. Running `php artisan db:seed --class=RolesAndPermissionsSeeder` directly (bypassing `DatabaseSeeder`'s trait) works fine, confirming the cause. Pre-existing bug, not caused by any specific feature — found while verifying migrations for the M2 Task feature. Not yet fixed; likely fix is dropping `WithoutModelEvents` from `DatabaseSeeder` or manually calling `app(PermissionRegistrar::class)->forgetCachedPermissions()` after `RolesAndPermissionsSeeder` runs.
