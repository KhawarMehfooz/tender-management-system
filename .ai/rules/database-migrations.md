---
paths:
  - 'database/migrations/**'
---

# Database Migrations

## Laravel's published notifications migration defaults to bigint notifiable_id
`php artisan notifications:table` publishes a migration using `$table->morphs('notifiable')`, which creates an unsigned-bigint `notifiable_id` — incompatible with this app's all-UUID domain PKs. Always edit the generated migration to `$table->uuidMorphs('notifiable')` before running it. Same class of trap as spatie/laravel-permission's bigint-default pivot tables (see [[models]]).
