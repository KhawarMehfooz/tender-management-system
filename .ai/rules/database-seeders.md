---
paths:
  - 'database/seeders/**'
---

# Database Seeders

## DatabaseSeeder's WithoutModelEvents breaks Spatie permission seeding — fixed
`DatabaseSeeder` uses `use WithoutModelEvents;`, which mutes model events for its entire `run()` — including everything called via `$this->call()`. Spatie's permission package invalidates its cache via a `Permission`/`Role` model-saved event, so with events muted, `RolesAndPermissionsSeeder`'s `Permission::findOrCreate()` inserts rows but the cached (stale/empty) permission collection never refreshes, and the immediately-following `Role::syncPermissions()` throws `PermissionDoesNotExist`. Reproduced reliably on a clean DB via `php artisan migrate:fresh --seed`. Fixed by adding an explicit `app(PermissionRegistrar::class)->forgetCachedPermissions();` call in `RolesAndPermissionsSeeder::run()`, right after the `Permission::findOrCreate()` loop and before `Role::syncPermissions()` — scoped to the one seeder that needs it, rather than dropping `WithoutModelEvents` from `DatabaseSeeder` (which still benefits the bulk `CpvCodeSeeder`/`NutsCodeSeeder` imports). If a similar seeder ever creates permissions/roles and then immediately checks/syncs them within the same `WithoutModelEvents`-wrapped run, it needs the same explicit cache-forget call.
