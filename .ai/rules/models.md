---
paths:
  - 'database/migrations/**,app/Models/**'
---

# Models

## All domain model primary keys are UUID (Postgres native uuid column)
Every domain/entity table uses a UUID primary key, not auto-incrementing bigint. Migration: `$table->uuid('id')->primary()`; foreign keys use `$table->foreignUuid('x_id')->constrained()`, never `foreignId()`. Model: `use HasUuids` (Laravel 13's HasUuids generates UUIDv7 — time-sortable — by default; no extra trait needed, and it auto-sets keyType/incrementing for you).

Scope: this applies to domain tables (users, tenders, tasks, etc.), not Laravel-owned infrastructure tables (cache, jobs, sessions' own `id`, password_reset_tokens, failed_jobs) — those keep their native schema since Laravel's internals depend on the exact format. `sessions.user_id` is the exception: it's a domain FK so it becomes `foreignUuid`.

Trap: Postgres cannot run `MAX()`/`MIN()` against uuid columns, so `ofMany()` one-of-many Eloquent relationships won't work on UUID-keyed tables — use an explicit `orderBy()`+`first()` query instead if that pattern is needed.

Trap: spatie/laravel-permission's published migration defaults roles/permissions/pivot tables to bigint ids — must be hand-edited to uuid to stay consistent once that package is installed.
