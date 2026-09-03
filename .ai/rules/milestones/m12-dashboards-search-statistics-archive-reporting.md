# M12 — Dashboards, Search, Statistics, Archive, Reporting

Full spec: [idea.md](../../../idea.md)'s M12 section. Index: [../milestones.md](../milestones.md).

**M12 — Dashboards, Search, Statistics, Archive, Reporting is now in progress**, started 2026-09-03.

Design decisions confirmed with the user before any code was written:
- **PDF/Excel export via `barryvdh/laravel-dompdf` + `maatwebsite/excel`** — the standard
  Laravel/Filament combo. dompdf renders Blade views straight to PDF (no headless-Chrome/Node
  dependency in the container, unlike `spatie/laravel-pdf`'s Browsershot approach);
  Laravel-Excel handles `.xlsx` export via `FromCollection`/`FromView` export classes. Both are
  new `composer.json` dependencies — approved explicitly, per [[general]]'s "don't change
  dependencies without approval" rule.
- **Three dashboards (employee/team lead/management) are one Filament `Dashboard::class` page,
  not three routes.** All widgets register on the existing built-in `Dashboard` (already wired
  in `AdminPanelProvider`); each widget's own `visible()`/`canView()` gates it to the right
  audience (role and/or `Right`), so an employee's render only shows their widgets, a team lead's
  adds theirs, management's adds theirs — same "gate by Right, not by route" pattern this app
  already uses everywhere else (M10/M11 pages, `PipelineForecast`'s `canSeePrices()`). No new
  post-login redirect logic needed.
- **Global search reuses Filament's built-in global search**, not a custom search page.
  `getGloballySearchableAttributes()`/`getGlobalSearchResultDetails()`/
  `getGlobalSearchResultUrl()` added to the resources covering idea.md's listed fields
  (`TenderResource`: internal ID, procurement number, title, service type via category name,
  city; `ClientResource`: name/procurement office; `CompetitorResource`: company name;
  `UserResource`: employee name; `TenderDocumentVersion`/document-bearing resources: filename).
  "Winner" (a plain string column on `TenderResult`, not its own resource) is exposed by adding
  it to `TenderResource`'s searchable attributes via a relationship path
  (`result.winner`) rather than inventing a `TenderResult` resource just for search. Respects
  each resource's existing category/right scoping automatically, since global search runs
  through the same `getEloquentQuery()` every list page uses.

Additional decisions made while scoping (not asked, low-stakes implementation choices — flag for
the user if any turns out wrong once built):
- **Statistics is a new gated `Filament\Pages\Statistics` page** (plain `Page`, not `HasTable` —
  mirrors `TeamPerformance`'s shape: `getViewData()` feeding several independent breakdown arrays
  to a Blade view, since idea.md's statistics list ("win rate, participation rate, bid volume,
  won/lost volume, average contract value and margin, average handling time, formal exclusions,
  deadline reliability, how far ahead of deadline bids are submitted, regional/sector/source
  analysis, loss reasons, price and competitor development") doesn't fit one table). No new right
  gating beyond the existing `ServiceCategoryScope`/`SEE_PRICES` — category-scoped users see
  their own category's numbers (the scope already restricts every `Tender::query()` call), a
  management user (null category) sees the cross-category totals, matching
  [[scopes-models]]'s established convention; price/margin-bearing figures (average contract
  value, average margin, price development) are individually hidden behind `Right::SEE_PRICES`
  the same way `PipelineForecast`'s volume/weighted-value columns already are. All stats computed
  live via query, no new stored columns or snapshot tables — same "derived analyses" precedent as
  every stats surface in M9-M11.
  - **Formal exclusions** is rendered as its own headline stat card (count + rate of
    `TenderStatus::EXCLUDED`, target-zero framing per idea.md) above the rest of the breakdowns,
    not buried in a table row.
  - **Deadline reliability / days-ahead-of-submission**: computed from `TenderSubmission.date`
    vs. the tender's `submission_deadline` (days ahead = deadline − actual submission date;
    negative means late) and `TenderDeadline` rows generally (met vs. missed, by comparing
    `deadline_at` against whatever closed it — reuses the same "overdue" concept
    `CheckDeadlineEscalations` already computes rather than inventing a second definition).
  - **Price and competitor development**: a simple period-over-period trend (this year vs. last
    year, grouped by quarter) of average bid price and competitor win counts, reusing
    `CompetitorPriceEntry`/`TenderResult` data already collected in M9/M10 — genuinely
    forward-looking trend analysis is out of scope, this is a factual breakdown table, not a
    forecast model.
- **Combinable filters live where the underlying list already lives**, not as one universal
  filter component: `TenderResource`'s table (already the natural home for
  period/department/status/employee/region/service category/sector/procedure/source/outcome/
  volume/competitor — idea.md's filter list is entirely tender-shaped) gains the missing
  `SelectFilter`/`Filter` instances for whichever of those it doesn't already have, and the new
  Archive/Statistics pages reuse the exact same filter query logic via a shared
  `App\Filament\Concerns\HasTenderReportFilters` trait (a `Schema`-returning `filtersForm()` plus
  an `applyTenderFilters(Builder $query, array $data): Builder` method) rather than three
  divergent copies of the same 12 filters. "Competitor" filters via `whereHas('competitors', ...)`
  against the existing `TenderCompetitor` pivot from M10.
- **Archive is a new `Filament\Resources\Tenders\Pages\ArchivedTenders` list page on the existing
  `TenderResource`** (not a separate resource — an archived tender is still a `Tender` row with
  its own lifecycle `status` visible, per idea.md's explicit "keeps its own status visible
  alongside the archive flag" instruction), with its own table query
  `Tender::query()->where('is_archived', true)` (the global `ServiceCategoryScope` still applies
  normally), the `HasTenderReportFilters` trait for combinable filtering, and columns showing
  `invalidity_reason`/`archived_at` alongside the normal status badge. Reachable via a
  `TenderResource::getPages()` entry + a table action/link from the main tender list ("View
  archive"), not its own top-level nav item — keeps `TenderResource`'s single nav entry, matching
  how `ArchivedTenders` is conceptually still "tenders," just filtered.
- **Deadline radar widget**: new `DeadlineRadarWidget` (a `Filament\Widgets\TableWidget` on the
  Dashboard), querying every non-completed `TenderDeadline` the current user can see (category
  scope inherited via the parent `Tender` relation, same as every other deadline surface), sorted
  ascending by `deadline_at` (soonest/most urgent first), capped to a reasonable page size
  (10 rows) — a "radar," not the full calendar (`TenderCalendar` already exists for that).
- **Activity feed widget**: new `ActivityFeedWidget` (`TableWidget`), a `UnionBuilder`-backed
  query merging the most recent N rows across `TenderStatusChange`, `TaskStatusChange`, and
  `TenderDocumentRequestStatusChange` (the three status-change log tables that already exist,
  per the M1-M9 audit-trail precedent) into one reverse-chronological feed, each row rendering
  "who changed what to what, when" with a link to the parent tender/task. No new "activity log"
  model — this is a read-time union over existing per-entity change logs, consistent with there
  being no single unified activity table anywhere else in the app.
- **Reports & exports**: new `Filament\Pages\Reports` page listing each report type from idea.md
  (pipeline, win/loss, competitors, employee/department performance, deadlines, management
  reporting) as a row with "Export PDF" / "Export Excel" actions. Each report's data comes from
  the query methods *already built* in earlier milestones/tasks rather than new duplicate
  queries: pipeline → `PipelineForecast::pipelineQuery()` (refactored to `public static` so this
  page can call it), win/loss → `TenderResult`/`WinLossReason` breakdown (new, small), competitors
  → `CompetitorIntelligence`'s existing derived-analysis queries (M10 task 4), employee/department
  performance → `TeamPerformance::departmentBreakdown()`/`rankings()` (refactored `public
  static` the same way), deadlines → `TenderDeadline` reliability stats from the Statistics task
  above, management reporting → a composite of the Statistics page's headline KPIs. PDF export
  renders a dedicated Blade view per report (dompdf via `Pdf::view(...)->download()`); Excel
  export uses one shared generic `App\Exports\ArrayExport implements FromArray, WithHeadings` fed
  the same row/column data most reports already shape as arrays for their Blade table, rather
  than hand-writing 6 separate Excel export classes with duplicated column logic. Every export
  action re-checks `Right::SEE_PRICES` before including price-bearing columns, same as their
  on-screen counterparts.
- **Automatic monthly/quarterly/annual reports**: new `ScheduledReport` model (UUID PK,
  `report_type` string, `period_type` enum — new `App\Enums\ReportPeriod`
  (`MONTHLY`/`QUARTERLY`/`ANNUAL`), `period_start`/`period_end` dates, `file_path` (private disk,
  same pattern as `TenderDocumentVersion`), `generated_at`, timestamps — never hard-deleted,
  matches every other "keep history" model in this app). New `GenerateScheduledReports` Artisan
  command (registered in `routes/console.php` via `Schedule::command(...)->monthly()` for the
  monthly management-summary report; quarterly/annual reports are the same command with a
  `--period=quarterly|annual` option, scheduled via `->quarterly()`/`->yearly()` — one command,
  three schedule entries, not three commands, since the generation logic is identical modulo date
  range) that renders the management-reporting PDF for the closed period and stores it, then
  notifies every `SUPER_ADMIN`/`DEPARTMENT_HEAD` via a new `ScheduledReportGeneratedNotification`
  (new `NotificationType::SCHEDULED_REPORT_GENERATED` case) linking to a download route (mirrors
  `TenderDocumentDownloadController`'s authenticated-stream-with-scope-check shape, scoped to
  `Right`-holders only, not per-tender category — a report spans categories by nature).
  `Reports` page gains a "Report history" table listing past `ScheduledReport` rows with a
  download action.

Planned tasks for M12:
- [x] **Task 1 — Export infrastructure**: installed via `composer require barryvdh/laravel-dompdf
  maatwebsite/excel` inside the `app` container (`barryvdh/laravel-dompdf v3.1.2`,
  `maatwebsite/excel 4.0.2`) — `composer require`'s own postinstall scripts ran
  `filament:upgrade` and `boost:update` automatically, the latter appending `docs`/`.ai` to
  `.dockerignore` (unrelated to this task, left as-is). New `App\Exports\ArrayExport`
  (`FromArray` + `WithHeadings`, constructor takes plain `array<int, array<int, mixed>> $rows` +
  `list<string> $headings`) for later tasks to share instead of one bespoke export class per
  report. Smoke-tested both packages via `php artisan tinker`: `Pdf::loadHTML(...)->output()`
  produces a valid `%PDF`-prefixed byte stream; `Excel::store(new ArrayExport(...), ...)` writes a
  readable `.xlsx` file — both confirmed then cleaned up, no throwaway code left behind beyond the
  permanent `ArrayExport` class itself.

  Trap hit while installing, not in app code: the container (and host) briefly had no route to
  `codeload.github.com`/`github.com` at all (network-level, outside this repo — Packagist itself
  was reachable throughout), which made the first `composer require` attempt hang on
  `composer/pcre`'s dist download. Resolved once the user's network access to GitHub was restored;
  no code or config change was needed once connectivity returned.

  No new tests (infrastructure-only task, nothing user-facing yet for a later task's test suite to
  regress against). Pint clean on the new file; `phpstan --memory-limit=1G
  app/Exports/ArrayExport.php`: no errors. Full `php artisan test --compact` run hit the same
  pre-existing memory-limit ceiling on this environment already documented at the end of M10/M11
  (unrelated to this task's code, no new failure introduced).
- [x] **Task 2 — Statistics page**: `App\Filament\Pages\Statistics`, in a new "Reporting" nav
  group (`lang/en/navigation.php`'s `groups.reporting`, added to plan for task 6's `Reports` page
  to share — not registered in `AdminPanelProvider::navigationGroups()`'s explicit ordering list,
  matching how `market_intelligence`/`people` already aren't listed there either, so no change
  needed there). No dedicated right gating — the page is visible to everyone; category scoping
  comes for free from `Tender`'s existing `ServiceCategoryScope` (a scoped user's queries only
  ever see their own category, a management user with `service_category_id = null` sees
  everything), matching [[scopes-models]]'s convention exactly.

  Regional/sector/source breakdown was **not** rebuilt here — `MarketAnalysis` (M10) already
  covers exactly that via its 6 chart widgets, so `Statistics` focuses on the metrics idea.md
  lists that nothing else in the app yet computes: win rate, participation rate, bid volume,
  won/lost volume, average contract value/margin, average handling time, formal exclusions
  (headline stat card, target-zero framing), deadline reliability + days-ahead-of-submission, a
  win/loss-reason tally (reusing M9's `TenderResult.win_loss_reasons`), and a 4-quarter price/
  competitor development trend. All computed live via `getViewData()` private static methods, no
  new stored columns. Price-bearing figures (won/lost volume, average contract value, average
  margin, the trend's average bid price) are gated behind `Right::SEE_PRICES` — margin figures
  specifically follow the app's *established* convention of gating under `SEE_PRICES`
  (`CalculationsRelationManager` does the same), not the separate `Right::SEE_MARGINS` case,
  which — confirmed by grepping the whole `app/` tree — has been defined in the `Right` enum since
  M1 but has never actually been enforced anywhere; introducing it now for just this one page
  would create an inconsistency rather than fix one, so it was deliberately left alone.

  Two traps hit and fixed: (1) `Tender::submissionDeadline()`/`TenderStatusChange` don't use
  `created_at` the way the design assumed — `TenderStatusChange` has `$timestamps = false` and its
  own `changed_at` column, so `averageHandlingTimeDays()` was fixed to order/read `changed_at`
  instead. (2) `$counts[$reason]++` (incrementing a keyed value inside a `Collection` via
  `ArrayAccess`) silently throws `Indirect modification of overloaded element has no effect` —
  `Collection`'s `ArrayAccess` doesn't support the compound-increment shorthand arrays do; fixed to
  the explicit `$counts[$reason] = $counts[$reason] + 1`.

  Also worth noting for later tasks: `TenderResult.win_loss_reasons` is PHPDoc'd as
  `list<WinLossReason>|null` but is actually a plain `'array'` cast holding raw strings — matches
  `ResultRelationManager`'s existing `WinLossReason::from($state)` usage, which only makes sense if
  the runtime values are strings, not enum instances. `lossReasonBreakdown()` re-declares the
  loop's local type via an inline `@var list<string>` rather than trusting the model's PHPDoc, so
  phpstan checks against the real runtime shape.

  Tests: `StatisticsPageTest` — win rate (50%) and participation rate (66.7%) reconcile against a
  3-tender fixture (1 won, 1 lost, 1 excluded, 2 of them `BID`-decided); a `SEE_PRICES`-lacking
  user sees `statistics.price_hidden` and not the formatted money value, a right-holder sees it;
  a category-scoped user's `formalExclusions` count only reflects their own category while a
  management user (`service_category_id = null`) sees both categories combined; submission-
  deadline reliability computes 100% for an on-time fixture; win/loss reasons recorded on a
  `TenderResult` render via their labels; average margin from a tender's current calculation
  renders correctly when `SEE_PRICES` is granted. 7 tests passing. Full `tests/Feature/Filament/
  Pages` regression run: 39 passing (up from 32). Pint clean; `phpstan --memory-limit=1G`: 15
  findings, all `staticClassAccess.privateMethod` — the same accepted baseline class used by every
  other `static::`-calling page in this app (M10/M11 precedent), not a new category.
- [x] **Task 3 — Combinable tender filters + Archive view**: `App\Filament\Concerns\
  HasTenderReportFilters` trait (`tenderReportFilters(): array`, returning plain Filament Table
  `Filter`/`SelectFilter` instances rather than a `Schema`-based `filtersForm()` — table filters,
  not dashboard-widget filters, so the framework's native `->filters([...])` mechanism is the
  right fit). `TendersTable.php` already had `service_category_id`/`status`/`source_id`
  (confirmed by reading it first, as scoped); the trait adds the rest of idea.md's 12: `sector_id`,
  `procurement_procedure_id`, `nuts_code_id` (region), `owner_id` (employee), a `period` range
  filter (`created_at` between two dates), a `estimated_contract_volume` min/max range filter, and
  a `competitor` filter (`whereHas('tenderCompetitors', ...)`). "Department" and "outcome" were
  *not* added as separate filters — this app has no distinct department concept beyond
  `ServiceCategory` (per [[scopes-models]]'s established "department" == service_category
  convention), and `TenderStatus` already includes every terminal outcome, so a second filter for
  either would just duplicate an existing field under a different label; documented directly in
  the trait's docblock so a future reader doesn't wonder why they're "missing." `TendersTable` now
  consumes `...static::tenderReportFilters()` instead of its 3 inline duplicates.

  New `App\Filament\Resources\Tenders\Pages\ArchivedTenders` (`ListRecords` subclass, registered
  as `TenderResource::getPages()`'s `'archive' => ArchivedTenders::route('/archive')`, placed
  before the `'view' => '/{record}'` route so it isn't shadowed) with its own `table()` — base
  query `Tender::query()->where('is_archived', true)`, the `ServiceCategoryScope` global scope
  still applies automatically since it's a normal `Tender` query, `HasTenderReportFilters` reused
  via `->filters(static::tenderReportFilters())`, columns for internal ID/title/status badge (kept
  visible per idea.md's explicit "keeps its own status visible alongside the archive flag"
  instruction)/`invalidity_reason`/`archived_at`, and a `ViewAction` linking back into the normal
  `TenderResource` view page. `ListTenders` gained a "View archive" header `Action` linking to it
  — no separate top-level nav item, matching the design decision that an archived tender is
  conceptually still "tenders," just filtered.

  Two phpstan findings fixed during cleanup (not baseline): `Competitor::find($id)?->name` doesn't
  narrow correctly against Eloquent's generic `find()` signature (can return a `Collection` when
  given an array) — replaced with an explicit `Competitor::query()->whereKey($id)->value('name')`.
  An initial `getTableQuery(): Builder|Relation|null` override on `ArchivedTenders` hit generic-type
  and `Table::query()` argument-type mismatches; simplified to inlining the query directly in
  `table()`'s `->query(fn (): Builder => ...)` instead of a separate override.

  Tests: `TenderResourceTest`'s new "combinable filters" group (volume range, period range,
  competitor, and a service-category + status AND-combination case, using Filament's
  `filterTable()` test helper). New `ArchivedTendersTest.php` — only archived tenders are listed,
  a won-and-archived tender still shows the `WON` status badge, category scoping is respected, and
  the shared filters combine correctly on this page too. 165 tests passing in the full
  `TenderResourceTest.php` regression run (up from 159), plus 4 new in `ArchivedTendersTest.php`.
  Pint clean; `phpstan --memory-limit=1G` across every touched file: 1 finding
  (`TendersTable.php:71`'s `orderBy($direction)` argument-type note), confirmed pre-existing and
  unrelated to this task's diff via `git diff` — the trait, `ArchivedTenders`, `ListTenders`, and
  `TenderResource` are all fully clean.
- [x] **Task 4 — Dashboard widgets**: 5 widgets, each gated via `canView()` rather than a shared
  `visible()` wrapper (matches Filament's own per-widget visibility hook):
  `EmployeeOpenTasksWidget` (`TableWidget`, no gating — everyone's own open tasks, soonest due
  date first, capped at 10), `DeadlineRadarWidget` (`TableWidget`, no gating — every upcoming
  `TenderDeadline` the user can see via its existing `TenderDeadlineCategoryScope`, ordered by
  `due_at` ascending, capped at 10), `ActivityFeedWidget` (plain `Widget` + Blade view, no
  gating — merges the most recent rows from `TenderStatusChange`/`TaskStatusChange`/
  `TenderDocumentRequestStatusChange` in PHP, sorted by `changed_at` descending, capped at 15;
  manually category-scoped per source since none of those three models carry their own global
  scope), `TeamLeadDepartmentOverviewWidget` (`StatsOverviewWidget`, gated to a `TEAM_LEAD`/
  `DEPARTMENT_HEAD` with a `service_category_id` set — deliberately role-gated, not
  `Right::VIEW_EMPLOYEE_STATISTICS`-gated, since per [[pages]] that right is only seeded to
  super-admin/department-head by default and this widget's own-department open/overdue/on-time
  figures are operational visibility, not the sensitive cross-employee ranking data the right was
  meant to protect), `ManagementKpiWidget` (`StatsOverviewWidget`, gated behind
  `Right::VIEW_EMPLOYEE_STATISTICS` — win rate, formal-exclusion count, open pipeline count).

  A real latent issue was found and fixed while wiring registration: `AdminPanelProvider` used
  `->discoverWidgets(in: app_path('Filament/Widgets'), ...)`, which populates
  `Filament::getWidgets()` — the exact list `Filament\Pages\Dashboard::getWidgets()` renders —
  with *every* widget class in the folder, including the 6 `MarketAnalysis` chart widgets and the
  2 calendar widgets that are only ever meant to appear on their own explicit pages
  (`MarketAnalysis::getFooterWidgets()`, `TenderCalendar::getWidgets()`). Confirmed via
  `Filament::getWidgets()` in tinker that all 8 were already silently appearing on the built-in
  Dashboard before this task touched anything — a pre-existing latent clutter bug, not something
  this task introduced, but one that would have made the new 3-cut dashboard actively unusable
  (13 widgets stacked on one page) if left as-is. Fixed by replacing `discoverWidgets()` with an
  explicit `->widgets([...5 new classes...])` — the 8 page-specific widgets are unaffected since
  they're attached to their pages independently of panel-level registration; confirmed via the
  same tinker check (now lists exactly 5) and a full `tests/Feature/Filament` re-run (359 passing,
  `MarketAnalysis`/`TenderCalendar`/`TeamPerformance` all still render their own widgets
  correctly).

  Tests: new `tests/Feature/Filament/Widgets/DashboardTest.php` — `canView()` gating verified
  directly per widget for a plain staff user (employee/radar/feed visible, team-lead/management
  not), a team lead with a department (team-lead widget visible, management not), and a
  `VIEW_EMPLOYEE_STATISTICS` holder (management visible); `DeadlineRadarWidget` sorts a 2-day-out
  deadline ahead of a 10-day-out one and excludes a different category's deadline for a
  category-scoped user; `ActivityFeedWidget` renders entries from both `TenderStatusChange` and
  `TaskStatusChange` sources together. 6 tests passing. Full `tests/Feature/Filament` regression:
  359 passing (up from 353). Pint clean; `phpstan --memory-limit=1G` across every widget file plus
  `AdminPanelProvider.php`: 0 new findings (the only findings in the wider `app/Filament/Widgets`
  directory are the pre-existing calendar-widget baseline noted in M11, untouched by this task).
- [x] **Task 5 — Global search**: `TenderResource::getGloballySearchableAttributes()` returns
  `internal_id`, `procurement_number`, `title`, `city`, `procurement_office`, plus dot-notation
  relation paths `client.name`, `serviceCategory.name` (service type), `owner.name` (employee),
  `documents.title` (document — no dedicated `TenderDocumentResource` exists per [[controllers]],
  so this is the only reasonable way to expose document search per the design decision),
  `result.winner`, and `tenderCompetitors.competitor.name` (competitor) — covers every field in
  idea.md's list. Confirmed multi-level dot paths (`tenderCompetitors.competitor.name`) work via
  Eloquent's native nested `whereHas()` dot-syntax, which Filament's `HasGlobalSearch` trait
  already builds on. Added `getGlobalSearchResultDetails()` too (internal ID, service category,
  status) for a more useful result card. `CompetitorResource` needed no changes — it already
  had `$recordTitleAttribute = 'name'`, which makes it globally searchable by name by default;
  confirmed via a passing test rather than assumed.

  `ClientResource` and `UserResource` had no `$recordTitleAttribute` set, so global search was
  silently off for both (`getGloballySearchableAttributes()`'s default falls back to `[]` with no
  title attribute, per Filament's own `HasGlobalSearch` trait). Fixed by adding
  `$recordTitleAttribute = 'name'` to both (mirroring `CompetitorResource`'s existing precedent —
  also fixes each resource's record title used elsewhere, e.g. delete-confirmation copy, not just
  search) plus an explicit `getGloballySearchableAttributes()` override on `UserResource` adding
  `email` on top of `name`. `UserResource`'s results are naturally restricted to the same audience
  as its list page — `canGloballySearch()` also checks `canAccess()` → `canViewAny()` →
  `canManage() || canViewStatistics()` — no separate gating needed.

  Trap hit and fixed: an initial test asserting `getGlobalSearchResults('Northgate Council')`
  contained the client's name failed even though the query itself found the record (confirmed via
  a raw query in tinker) — `getGlobalSearchResultTitle()` defaults to
  `$record->getAttribute(static::getRecordTitleAttribute()) ?? static::getModelLabel()`, so with no
  title attribute set, every result's title silently became the generic model label ("Client"),
  never the actual record name. Setting `$recordTitleAttribute` fixed both the title and, as a
  side effect, made the redundant explicit `getGloballySearchableAttributes()` override on
  `ClientResource` unnecessary (removed rather than left as dead code).

  Tests: new `GlobalSearchTest.php` — a tender is found by internal ID/city/procurement office
  directly, and via client/owner/service-category/document/result-winner/competitor relations; a
  category-scoped user's search excludes another category's tender; a client is found by name; a
  competitor is found by name for a `SEE_COMPETITOR_DATA` holder and `canGloballySearch()` is
  false without it; an employee is found by name or email for a user who can manage users, and
  `canGloballySearch()` is false for a plain staff user. 8 tests passing. Regression: full
  `TenderResourceTest.php` (165), `ClientResourceTest`/`UserResourceTest` (32), `ArchivedTendersTest`
  (4), `DashboardTest` (6), and the full `Pages` test directory (39) all re-run clean — the
  directory-wide `tests/Feature/Filament` run hit the same pre-existing environment memory-limit
  ceiling documented at the end of M10/M11 (unrelated to this task), so every affected file was
  run directly instead. Pint clean; `phpstan --memory-limit=1G` on every touched resource file: 6
  findings, all the pre-existing `canManage()`/`canViewStatistics()` baseline on `UserResource.php`
  (confirmed unchanged via `git diff`), `TenderResource.php` and `ClientResource.php` fully clean.
- [x] **Task 6 — Reports & exports page**: `App\Filament\Pages\Reports`, one row per report type
  (pipeline, win/loss, competitors, employee/department performance, deadlines, management
  reporting) rendered from `getViewData()`, each with `exportPdf`/`exportExcel` header-style
  actions triggered via `wire:click="mountAction('exportPdf', { report: '<key>' })"` (per Filament's
  documented "programmatically triggering actions" pattern — `Filament\Pages\Page` already includes
  `HasActions`/`InteractsWithActions`, so no extra wiring needed). `PipelineForecast::pipelineQuery()`
  and `TeamPerformance::departmentBreakdown()`/`rankings()` refactored `private` → `public static`
  (mechanical visibility-only change, call sites updated to `static::`); `Statistics`'s
  `formalExclusions()`/`winRate()`/`participationRate()`/`bidVolume()`/`wonLostVolume()`/
  `averageContractValue()`/`averageMargin()`/`averageHandlingTimeDays()`/`deadlineReliability()`
  similarly made `public static` for the "management reporting" and "deadlines" report rows to
  reuse. New small win/loss (`Tender::whereIn('status', [WON, LOST])` with `result`) and
  competitor-summary (mirrors `CompetitorIntelligence`'s table columns as an array-mapping query,
  a new method rather than refactoring that page, since the milestone design explicitly called
  these out as new small methods) queries live directly on `Reports`, not extracted elsewhere.

  Both export actions share one path: a `rowsFor(string $key)`/`headingsFor(string $key)` pair
  builds a generic `(headings: list<string>, rows: array<array>)` shape per report, price-bearing
  columns/rows included only behind `canSeePrices()` — re-checked inside the action itself, not
  just the on-screen row, matching the design decision's "every export action re-checks
  Right::SEE_PRICES" instruction. `competitors`/`performance` report rows are hidden entirely from
  users lacking `SEE_COMPETITOR_DATA`/`VIEW_EMPLOYEE_STATISTICS` (mirrors those rights gating
  `CompetitorIntelligence`/`TeamPerformance` themselves), and `canExport()` re-checks the same right
  server-side when an action is invoked directly, not just via the hidden UI row. Excel export
  reuses `ArrayExport` (task 1) via `Excel::download()`; PDF export uses one shared generic Blade
  partial (`resources/views/reports/partials/table.blade.php`, plain HTML/inline CSS — no Tailwind,
  dompdf doesn't process the app's Vite-built theme) included by 6 thin per-report view files
  (`resources/views/reports/{pipeline,win-loss,competitors,performance,deadlines,management}.blade.php`),
  keeping one dedicated view file per report type as the design called for while avoiding 6x
  duplicated table markup.

  Two traps hit and fixed: (1) `Barryvdh\DomPDF\Facade\Pdf` has no static `view()` method, only
  `loadView()` — `Pdf::view(...)` doesn't exist, fixed to `Pdf::loadView(...)`. (2) Livewire's
  `SupportFileDownloads` component hook only recognizes a returned `StreamedResponse` or
  `BinaryFileResponse` as a downloadable effect (`valueIsntAFileResponse()` checks only those two
  types) — dompdf's own `->download()` returns a plain `Symfony\Component\HttpFoundation\Response`,
  which Livewire instead tried to JSON-serialize as a normal return value, throwing "Malformed
  UTF-8 characters" on the raw PDF binary. Fixed by wrapping the PDF output in
  `response()->streamDownload(...)` (a `StreamedResponse`) instead of returning dompdf's response
  directly; the Excel action already returned `BinaryFileResponse` natively, no change needed
  there.

  Tests: `ReportsPageTest` — competitors/performance rows hidden from a plain staff user, shown to
  a department head; calling `exportPdf` directly with a gated report key as a staff user 403s
  (tested via `->call('mountAction', ...)->assertForbidden()` — Filament's own `->callAction()`
  test helper doesn't survive an `abort_unless()` thrown mid-mount cleanly, so the lower-level
  Livewire `mountAction` call is used instead); every one of the 6 report types downloads a PDF via
  `assertFileDownloaded()`; every report type downloads an Excel file via `Excel::fake()` +
  `Excel::assertDownloaded()` (name-only `assertFileDownloaded()` on the Livewire side, since
  `ExcelFake::download()` returns a fixed fake file with no real `Content-Disposition` header —
  filename is instead asserted via `Excel::assertDownloaded($filename)`); the management export's
  actual exported rows omit price-bearing metrics for a `SEE_PRICES`-lacking user and include them
  for one who holds the right, asserted directly on `$export->array()` inside
  `Excel::assertDownloaded()`'s callback, not just on the download succeeding. 17 tests passing.
  Regression: `tests/Feature/Filament/Pages` (57 passing, up from 40) and the three refactored
  pages' own test files re-run clean. Pint clean; `phpstan --memory-limit=1G` on every touched
  file: only the same pre-existing `staticClassAccess.privateMethod` baseline every other
  `static::`-calling page already carries (confirmed via `git diff` that `Statistics.php`/
  `TeamPerformance.php`'s diffs are visibility-keyword-only).
- [ ] **Task 7 — Scheduled reports**: `App\Enums\ReportPeriod` (`MONTHLY`/`QUARTERLY`/`ANNUAL`,
  `HasLabel`). Migration + model `ScheduledReport` (never hard-deleted). `GenerateScheduledReports`
  command (`--period=` option), three `Schedule::command(...)` entries in `routes/console.php`
  (`->monthly()`, `->quarterly()`, `->yearly()`). `NotificationType::SCHEDULED_REPORT_GENERATED`
  case + `ScheduledReportGeneratedNotification`, sent to every `SUPER_ADMIN`/`DEPARTMENT_HEAD`.
  Download route/controller mirroring `TenderDocumentDownloadController`'s auth+stream shape,
  gated on holding at least one of the management rights (confirm exact gate once building — likely
  `Right::VIEW_EMPLOYEE_STATISTICS` or a new dedicated right, decide with the user if genuinely
  ambiguous). `Reports` page (task 6) gains a "Report history" table.

  Tests: `GenerateScheduledReportsTest` — running the command creates a `ScheduledReport` row +
  file + notifications for the right users, for each `--period` value; the download route 403s for
  a user without the gating right and streams correctly for one who has it.
- [ ] **Task 8 — Demo seeding & docs**: extend `DemoDataSeeder` only if any new task's acceptance
  point needs seeded data it doesn't already have (e.g. enough closed tenders across statuses to
  make Statistics/Archive/Reports non-empty on a fresh seed — check after tasks 1-7 land, this may
  already be satisfied by existing seeding). New `docs/17-dashboards-search-reporting.md`
  (general/public audience, matching pages 14-16's precedent, HTML-comment screenshot placeholders
  per [[docs]]) covering the three dashboard widgets, global search, Statistics, Archive, and the
  Reports page including scheduled reports. Cross-link from wherever an earlier docs page already
  touches the same surface (e.g. `03-managing-tenders.md`'s archive/invalidation section, if one
  exists) per [[docs]]'s sync rule.

Execute one task at a time, confirming with the user before moving to the next, per
[[general]]'s milestone workflow.
