---
paths:
  - 'docs/**'
---

# Docs

## Customer documentation tracker
Customer-facing product documentation (not architecture docs) is being written in `docs/*.md` in this repo, in customer-workflow order (not build/milestone order), for an eventual MkDocs Material + GitHub Pages site — see [idea.md](../../idea.md) for the underlying feature spec and [[milestones]] for build status. Written in plain English, no marketing fluff, and no em dashes anywhere (not even to join two clauses) — write full, separate sentences instead, the way conventional documentation is written. Screenshots go alongside the seeded demo data (`DemoDataSeeder`, already built) — the user drops finished `.jpg`/`.png` files into `screenshots/` at the repo root; drafting a section should leave an HTML-comment placeholder at each image spot (`<!-- screenshot: <route name or URL path> — <what to capture, incl. which demo account/state to be logged in as, and any UI state like an open modal> -->`), and once the user adds files, move them into `docs/screenshots/` and swap each placeholder for a real `![alt](screenshots/file.jpg)` immediately followed by an italicized caption line (`*Caption text.*`) below it — don't parse or "look at" the images, just place and caption them from the comment's own description and the filename.

Planned sequence (write one at a time, not all at once — confirm with the user before starting each):
- [x] 01 - Overview: what the system is, who it's for, the core tender-lifecycle concept — drafted at `docs/01-overview.md`, audience is both prospects and onboarding users in one doc, product referred to as "Tender Management System (TMS)", German/EU procurement terms (CPV/NUTS, etc.) kept as-is rather than translated
- [x] 02 - Getting started: login, roles overview, category scoping (why a user may only see some tenders) — drafted at `docs/02-getting-started.md`, full 9-role table with one-line purpose each, mentions accounts are admin-provisioned (no self-signup), uses `<!-- screenshot: ... -->` HTML-comment placeholder markers at the spots needing images
- [x] 03 - Managing tenders: creating a tender, lifecycle/status flow, archiving/invalidating, viewing history — drafted at `docs/03-managing-tenders.md`, walks all 5 wizard steps (Team step deferred to 04), one-line mention that estimated contract volume needs the see-prices right, covers archive/invalidate for regular users with hard-delete mentioned briefly as admin-only (full detail deferred to 08)
- [x] 04 - Team assignment: assigning an owner and team members to a tender — drafted at `docs/04-team-assignment.md`, covers both who can edit (team lead/department head/super admin) and the read-only view for others, one-line-per-role table for the 5 functional roles
- [x] 05 - Tasks: creating tasks, checklists, statuses, dependencies, due dates — drafted at `docs/05-tasks.md`, covers all 4 involvement roles (creator/owner/reviewer/participants), full status chain including the waiting-on-another-task branch and correction-required review loop, and dependencies
- [ ] 06 - Collaboration: comments and attachments on tasks
- [ ] 07 - Notifications: the notification centre, email preferences
- [ ] 08 - Administration: user management, roles & rights, lookup tables (service categories, sources, CPV/NUTS codes)

Before writing each section, ask the user clarifying questions specific to that section (audience assumptions, what to emphasize, what screenshots they already have, terminology preferences) rather than assuming. Update the checkboxes above as sections are drafted. Hosting/tooling decision (recorded 2026-08-27): docs live as plain `.md` in `docs/`, not the GitHub Wiki (which is a separate auto-repo, awkward to run a generator against) — deploy via MkDocs Material + GitHub Pages when ready, free for public repos.
