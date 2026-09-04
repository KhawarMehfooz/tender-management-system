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

## Plan new milestones into their own file under .ai/rules/milestones/, not a scratch plan file
`.ai/rules/milestones.md` is just an index table (one row per milestone, linking to
`.ai/rules/milestones/m<n>-<slug>.md`) — every milestone, complete or not, gets its own file
there. When asked to plan the next milestone (M4+), don't write it to a Claude Code scratch
plan file (e.g. ~/.claude/plans/*.md) — that's invisible to the next session. Instead:
1. Read idea.md's spec for that milestone plus every .ai/rules/* file whose glob will cover the new code.
2. Resolve genuine open design decisions with AskUserQuestion (recommended option first) before writing anything.
3. Create `.ai/rules/milestones/m<n>-<slug>.md` (slug from the milestone's idea.md title, kebab-case) and write the task breakdown into it, in the exact style already used for M2–M4: a top line back to idea.md and the index (`../milestones.md`), a "**M<n> — <name> is now in progress**" paragraph, a short list of the confirmed design decisions (with rejected alternatives noted), then a "Planned tasks for M<n>:" list of unchecked `- [ ]` items — one per incremental task, each detailed enough (models/columns/gating/tests) that a cold session could execute it without re-deriving the design.
4. Add a row for it to the table in `.ai/rules/milestones.md` (status "In progress").
5. Execute one task at a time, confirming with the user before moving to the next (same rhythm M3 used). After each task lands, check its box and expand it into a full paragraph documenting what was actually built, any traps hit, and the tests added — mirroring every existing M1–M4 entry.
6. Once all tasks are checked, add a "M<n> — <name> is now complete" line to the milestone's own file and flip its status to "Complete" in the `milestones.md` index table; don't build ahead into the next milestone without the user asking explicitly.
