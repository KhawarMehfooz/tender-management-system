---
paths:
  - '**/*'
---

# General

## Run artisan/composer commands inside the Docker container
This app runs in a custom Docker Compose setup (not plain Sail's default compose file), container/service name is `app` (container: `tms-app`). Bare `php artisan ...` or `composer ...` on the host will not work reliably (wrong PHP version/extensions, no DB connectivity to the `postgres` service host). Always run:
- `docker compose exec app php artisan ...`
- `docker compose exec app composer ...`

Check containers are up first with `docker compose ps`. Never manually create files that an artisan generator command (`make:model`, `make:migration`, `make:filament-resource`, etc.) can produce — always use the real command via `docker compose exec app`, then edit the generated file, per the Boost "Do Things the Laravel Way" rule.

## Ask before trying to verify visuals/rendering yourself
Do not try to verify UI appearance, layout, or rendering by proxy (curl/tinker HTTP kernel calls, reading raw HTML output, etc.) when uncertain how something looks. The user reviews visuals themselves in the browser. If confused about how a page renders or whether a design choice reads well, ask the user directly rather than attempting to inspect it programmatically — they'll tell you what they see and what to change.

This does not apply to functional verification (tests, Larastan, Pint, artisan commands) — keep doing those proactively. It applies specifically to "does this look right" questions.
