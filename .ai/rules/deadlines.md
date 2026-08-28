---
paths:
  - 'app/Models/TenderDeadline.php,app/Models/Tender.php,app/Enums/DeadlineType.php,app/Enums/EscalationLevel.php,app/Console/Commands/CheckDeadlineEscalations.php,app/Filament/Resources/Tenders/RelationManagers/DeadlinesRelationManager.php,app/Filament/Pages/TenderCalendar.php,app/Filament/Widgets/TenderDeadlineCalendarWidget.php'
---

# Deadlines & Escalation (M3)

## `tender_deadlines` allows multiple rows per type; no unique constraint
`TenderDeadline` (`tender_id` FK `cascadeOnDelete`, `type` → `DeadlineType`, `due_at`) has no
unique constraint on tender+type — a rescheduled submission deadline or several document
requests can each add another row rather than overwrite one. `Tender::submissionDeadline()`
picks the *latest* `SUBMISSION` row by `due_at` as the canonical one wherever "the" submission
deadline is needed (countdown display, calendar, escalation) — never assume a tender has at
most one deadline per type when querying directly.

`DeadlineType::BID_VALIDITY` is derived, not user-entered: `Tender::syncBidValidityDeadline()`
upserts a single row at `submissionDeadline()->due_at + bid_validity_days` (via
`updateOrCreate(['type' => BID_VALIDITY], ...)`), deleting it once either input goes unknown.
It's called from `CreateTender`/`EditTender`'s `afterCreate()`/`afterSave()` after the wizard's
transient submission-deadline field is written via `Tender::upsertDeadline()`. Excluded from
`DeadlinesRelationManager`'s manageable type options for this reason — a manual row would just
be silently overwritten on the next tender save.

## `TenderDeadline`/`Task` escalation columns are forceFill-only, one-directional
`escalation_level`/`last_escalated_at` on both `tasks` and `tender_deadlines` are deliberately
excluded from each model's `#[Fillable(...)]` list — only `CheckDeadlineEscalations` writes them,
via `forceFill()`. State only ever moves forward (the highest level reached so far) and is never
reset — there's no "un-escalate" path yet. `Task.escalation_level` only ever holds
`ASSIGNEE`/`TEAM_LEAD` (levels 1-2, task-overdue); the tender's canonical `SUBMISSION` row's
`escalation_level` only ever holds `ADMINISTRATOR`/`MANAGEMENT` (levels 3-4,
submission-deadline-driven). Each level fires its notification at most once per task/tender.

## Escalation scheduler: hourly, no distinct administrator/management role
`App\Console\Commands\CheckDeadlineEscalations` (`tenders:check-deadline-escalations`),
scheduled hourly in `routes/console.php`, this app's first scheduled command. Two independent
passes:
- **Overdue tasks** (levels 1-2): any open (`status != DONE`) task past `due_date` notifies its
  owner unconditionally (level 1), then the tender's `owner_id` once the task has been overdue
  ≥24h (level 2) — both can fire in the same run after downtime, which is intentional catch-up
  behavior, not a bug.
- **Submission deadlines** (levels 3-4): only runs for tenders with at least one open
  `TaskPriority::URGENT` ("critical") task and a future canonical `submissionDeadline()`. Notifies
  every `RoleName::SUPER_ADMIN` user (this app has no distinct administrator/management role) at
  ≤48h remaining (level 3), then again at ≤24h (level 4).

Guard `User::role(RoleName::SUPER_ADMIN->value)` with a `Role::where('name', ...)->exists()` check
first — `User::role()` throws `RoleDoesNotExist` outright when the role hasn't been seeded, which
real tests (not just a theoretical fresh install) hit.

## Tender calendar: category scope is automatic, filters reuse Dashboard widget-filter machinery
`App\Filament\Pages\TenderCalendar` + `TenderDeadlineCalendarWidget` (a `guava/calendar`
`CalendarWidget`) don't need a bespoke filter-wiring mechanism — the page uses
`HasFiltersForm`/`filtersForm()` and the widget uses `InteractsWithPageFilters` (`#[Reactive]
public ?array $pageFilters`), the same pattern Filament's own Dashboard widgets use. Category
scoping needs no extra code either: `getEvents()` queries `TenderDeadline::query()`, which is
already restricted by the existing `TenderDeadlineCategoryScope` global scope (mirrors
`TaskTenderCategoryScope`, see [[scopes-models]]) — don't add a second scoping layer here.

The "department" filter idea.md's spec mentions was deliberately skipped — this app has no
department concept distinct from `ServiceCategory`, and mapping one onto the other wasn't
assumed without the user confirming it first.

## Docs
Deadlines, the tender calendar, and escalation are documented for end users in
`docs/03-managing-tenders.md` under "Deadlines, the calendar & escalation". If any deadline
type, the calendar's filters, or the escalation thresholds/recipients change, update that
section too — see [[docs]].
