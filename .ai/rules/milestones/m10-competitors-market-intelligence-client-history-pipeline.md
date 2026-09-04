# M10 — Competitors, Market Intelligence, Client History, Pipeline

Full spec: [idea.md](../../../idea.md)'s M10 section. Index: [../milestones.md](../milestones.md).

**M10 — Competitors, Market Intelligence, Client History, Pipeline is now in progress**, started
2026-09-02.

Design decisions confirmed with the user before any code was written:
- **`Client` becomes a first-class lookup model**, not string-matching on
  `Tender.contracting_authority`. New `clients` table (id, name, region/notes fields TBD at
  task 1, `active` flag — same shape as `Source`/`Sector`), nullable `tenders.client_id` FK
  added alongside the existing `contracting_authority` string column (kept as-is, still the
  required free-text field on the tender form — `client_id` is an additional, optional link,
  not a replacement). A one-off backfill command creates one `Client` per distinct existing
  `contracting_authority` value and links matching tenders. Going forward, `TenderForm` gets an
  optional, creatable `client_id` Select alongside the existing `contracting_authority` text
  input. Rationale: M10's Client History needs a real entity to attach reminders/history to,
  and idea.md's M13 note says not to block a later structured-data bolt-on — this is that
  bolt-on, done now that M10 actually needs it (mirrors the `TenderResult.winner`-stays-a-string
  reasoning from M9, now resolved the way that entry anticipated).
- **Competitor pricing and win/loss track record are child models, not JSON blobs.**
  `CompetitorPriceEntry` (competitor_id, price, `source` NOT NULL per idea.md's compliance
  requirement, observed_on date, context/notes) gives full sourced history for M12's later
  "price development" reporting. A `tender_competitors` pivot (tender_id, competitor_id,
  outcome enum, known_price nullable, notes) links a `Competitor` to specific `Tender` rows
  they were seen on/competed against, feeding the derived analyses below. Both gated behind
  `Right::SEE_COMPETITOR_DATA` (already exists from M1, unused until now).
- **Pipeline's "internal win probability" reuses M6's `TenderParticipationScore`**, normalized
  to a 0-100%/0-1 figure, rather than a second independent number a user could set
  inconsistently with the bid/no-bid score. No new manually-entered probability field.
- **This milestone builds data + plain filterable Filament table pages for derived
  analyses/market analysis — no chart widgets, no exports.** M12 ("Dashboards, Search,
  Statistics, Archive, Reporting") is the dedicated milestone for chart-based dashboards, global
  search, and PDF/Excel export; M10 only needs the underlying models/queries to exist and be
  inspectable, not the polished presentation layer. Avoids overlap/rework between the two
  milestones.

Additional decisions made while scoping (not asked, low-stakes implementation choices — flag
for the user if any turns out wrong once built):
- **Competitor fields follow idea.md's list directly**: `name` (required), `region` (string),
  `service_areas` (nullable text — free-form list of service types, not FK'd to
  `ServiceCategory` since a competitor's own service breakdown doesn't need to align with this
  app's configurable-category model), `known_clients` (nullable text), `strengths`,
  `weaknesses` (nullable text each), `market_segments` (nullable text), `internal_notes`
  (nullable text). `CompetitorResource` sits in the same "Master Data"-adjacent navigation
  group as References/Certificates, gated `Right::SEE_COMPETITOR_DATA` on the resource itself
  (`shouldRegisterNavigation()`/`canViewAny()`), not role-gated — matches the "individually
  assignable rights, not role-based" M1 contract-level rule.
- **`tender_competitors.outcome` is a small enum** (`WE_WON`, `THEY_WON`, `UNKNOWN`) rather
  than free text, so the derived "who beats us repeatedly" analysis in task 4 can aggregate on
  it directly. Entered via a `CompetitorsRelationManager` on `TenderResource` (grouped into the
  existing "Engagement" `RelationGroup` per [[filament-resources-tenders]] — a tender's
  competitor sightings are collected during the same engagement phase as communication/site
  visits) and mirrored read-only on `CompetitorResource`'s own view (a "tenders faced" table).
- **Client history reminders (12/9/6 months before a known `contract_end_date`) run as a new
  scheduled Artisan command**, same shape as `CheckDeadlineEscalations` ([[deadlines]]), added
  to the scheduler alongside it. Scans `Tender::query()->whereNotNull('contract_end_date')`
  (explicitly not filtered by status, since idea.md says lost tenders count too), computes
  months-until-end, and notifies the tender's `owner` plus any `TEAM_LEAD`/`DEPARTMENT_HEAD`
  in the tender's `service_category` the first time each of the 3 thresholds is crossed. A new
  `tender_client_reminders_sent` join-ish tracking (simplest: 3 nullable timestamp columns
  `reminder_12_months_sent_at`/`reminder_9_months_sent_at`/`reminder_6_months_sent_at` on
  `tenders`, mirroring the one-directional/no-reset escalation-state pattern already used for
  `Task.escalation_level`/`TenderDeadline.escalation_level`) prevents re-notifying every day
  once a threshold is crossed. New `NotificationType::CLIENT_CONTRACT_RENEWAL` case and
  `ClientContractRenewalReminderNotification` class following the existing dual-channel
  pattern ([[notifications]]).
- **Derived analyses page** (task 4): one Filament `Page` (`CompetitorIntelligence` or similar,
  `HasTable`) with a table of competitors annotated with computed columns — encounter count,
  wins-against-us count, losses-to-us count, most common region/sector overlap with our own
  tenders — derived from `tender_competitors` + `Competitor`, no new stored columns. Gated
  `Right::SEE_COMPETITOR_DATA` per [[pages]]'s `canAccess()`-must-be-manually-checked rule.
- **Market analysis page** (task 5): one Filament `Page` with a small set of "group by"
  breakdown tables (region, sector, service category, client, source, procurement procedure —
  each a simple `groupBy()` count/sum query against `Tender`), not a single mega-table. No new
  models needed — this task is pure read/aggregate over existing M1–M9 data.
- **Client history** (task 6, bundled with the reminder command since both need
  `contract_end_date` scanning logic): `ClientResource` gets a `ViewClient` page (infolist plus
  a read-only "Tenders" relation-manager-style table scoped to `client_id`) showing the
  procedures/win-loss record/past winners/typical competitors idea.md asks for, all derived
  from the linked `Tender` rows (via `TenderResult.winner`, `tender_competitors`) — no new
  Client-owned columns beyond `name`/`region`/`active`/`notes`.
- **Pipeline & forecast page** (task 7): one Filament `Page` listing non-terminal tenders
  (`!TenderStatus::isTerminal()`) with computed columns: `estimated_contract_volume` (the
  existing `SEE_PRICES`-gated field), normalized win probability (from
  `TenderParticipationScore`, task-2's helper), weighted value (`volume × probability`),
  `contract_start_date`, and a simple resource-check indicator (team size vs.
  `estimated_contract_volume`/`TeamRole` coverage — best-effort flag, not a real staffing
  system, since no capacity/recruitment model exists yet and idea.md doesn't specify one).
  Totals row for weighted pipeline sum.

Planned tasks for M10:
- [x] **Task 1 — Client lookup model**: migration `clients` (UUID PK, `name` string, `region`
  nullable string, `notes` nullable text, `active` boolean default true, timestamps). Model
  `Client` (mirrors `Source`, plus a `tenders(): HasMany` for task 6's client-history view).
  `ClientResource` (list/create/edit, same lookup-table pattern as `SourceResource`/
  `SectorResource` — `OutlinedBuildingOffice2` icon, "Master Data" nav group, sort order 5 right
  after Sources, `canDelete()`/`canDeleteAny()` hardcoded `false` with the `EditClient` header
  actions emptied out, same rationale as Source: never hard-deleted, only deactivated, to
  preserve client-history integrity). Migration adds nullable `tenders.client_id`
  (`foreignUuid(...)->nullable()->constrained()->nullOnDelete()`, placed right after
  `contracting_authority`) + `Tender::client(): BelongsTo`. New Artisan command
  `tenders:backfill-clients` (`App\Console\Commands\BackfillTenderClients`) that groups tenders
  with a `null` `client_id` by distinct `contracting_authority`, `firstOrCreate()`s one `Client`
  per distinct value (safe to re-run — reuses a same-named `Client` rather than duplicating, and
  never touches a tender that already has a `client_id`), and links matching tenders — run
  manually against the dev DB, not part of `DatabaseSeeder`. `TenderForm` gains an optional
  (not required) `client_id` Select right after `contracting_authority`, filtered to active
  clients via `relationship('client', 'name', fn (Builder $query) => $query->where('active',
  true))`, no `createOptionForm` (matches every other lookup Select on this form — clients are
  managed on their own resource). New lang file `lang/en/clients.php`; new
  `tenders.fields.client_id` key. `docs/08-administration.md` gained a `Clients` row in its
  reference-data table (already dated today, no timestamp bump needed).

  Trap avoided: `fake()->state()` isn't in Faker's `Generator` docblock (only locale-specific
  providers define it), so PHPStan flags it as an undefined method even though it works at
  runtime — `ClientFactory`'s `region` field uses `fake()->randomElement([...German states...])`
  instead, both to stay PHPStan-clean and to fit the German-market domain better than Faker's
  US-state provider would have.

  Tests: `ClientResourceTest` (create with valid data, unique-name rejection, `canDelete()`/
  `canDeleteAny()` both false, no delete action on the edit page — mirrors
  `ServiceCategoryResourceTest`'s pattern), `BackfillTenderClientsTest` (creates one client per
  distinct authority and links matching tenders, safe to re-run without duplicating clients or
  re-linking, leaves an already-linked tender's `client_id` untouched even when another tender
  shares its authority string), and one new `TenderResourceTest` case asserting `client_id`
  round-trips through the create wizard alongside the free-text `contracting_authority`. 472
  tests passing (up from 464 — 8 new). Pint clean; `phpstan --memory-limit=1G` clean on every
  file this task created/edited (the pre-existing `TenderForm.php` `scopedUserOptions()`
  findings at lines 256-268 and the pre-existing Pest-`$this`-typing false positives are
  unrelated, confirmed by checking this task's added lines specifically).
- [x] **Task 2 — Competitor model + price entries**: migration `competitors` (UUID PK, `name`
  string, `region`/`service_areas`/`known_clients`/`strengths`/`weaknesses`/`market_segments`/
  `internal_notes` nullable text/string per field list above, timestamps). Model `Competitor`
  with a `priceEntries(): HasMany` (ordered newest-`observed_on`-first) — deliberately no
  `tenders()`/pivot relation yet, since `tender_competitors` doesn't exist until task 3.
  `CompetitorResource` gated end-to-end behind `Right::SEE_COMPETITOR_DATA` via a private
  `canManage()` consulted from `canViewAny()`/`canCreate()`/`canEdit()`/`canDelete()`/
  `canDeleteAny()` — exact same single-right, whole-resource shape as
  `CertificateResource`/`MANAGE_CERTIFICATES` (including the same pre-existing
  `staticClassAccess.privateMethod` PHPStan finding class that `CertificateResource` already
  carries — confirmed identical baseline, not a new regression). New "Market Intelligence" nav
  group (`lang/en/navigation.php`) since M7's "Reference Library"/M1's "Master Data" don't fit
  this domain; houses `CompetitorResource` now and every later M10 page (tasks 4/5/7).
  `OutlinedFlag` icon. `ViewCompetitor` page added (list/create/edit alone aren't enough once a
  relation manager exists — mirrors `TenderResource`'s view+edit split, not `ClientResource`'s
  edit-only shape which has no relation manager).

  Migration `competitor_price_entries` (UUID PK, `competitor_id` FK `cascadeOnDelete`, `price`
  decimal(12,2), `source` string NOT NULL, `observed_on` date nullable, `context` nullable
  text, `created_by` FK users `restrictOnDelete`, timestamps). Model `CompetitorPriceEntry`
  (`created_by` included in `#[Fillable]` despite never being form input — same
  server-stamped-via-`mutateDataUsing` pattern as `TenderResult.created_by`; caught by a
  failing test when first omitted, since silently-dropped mass-assignment doesn't error until
  the NOT NULL constraint fires). `PriceEntriesRelationManager` (append-only: `EditAction`
  exists, no `DeleteAction` at all, same shape as `CommunicationRelationManager`) gated
  per-action on the same `Right::SEE_COMPETITOR_DATA` check (technically redundant given the
  page-level gate already implies it, kept anyway as explicit self-documentation, unlike
  `EditCompetitor`'s bare `DeleteAction` which relies on the implication directly — see that
  file's docblock for the reasoning). `source` is `->required()` on the form, enforcing idea.md's
  compliance requirement that every competitor price be traceable.

  Added `TenderParticipationScore::winProbability(): ?float` — `score()` normalized to 0.0-1.0,
  null when the score itself is incomplete — so task 7's pipeline weighting reuses this instead
  of a second, independently-settable probability field (per the confirmed design decision
  above).

  New lang files `competitors.php`, `competitor_price_entries.php`; new
  `navigation.groups.market_intelligence` key.

  Trap avoided: `fake()->state()` isn't PHPStan-resolvable (see task 1's note) — `region` in
  both `ClientFactory` and `CompetitorFactory` now share the same German-states
  `randomElement()` fixture list.

  Tests: `CompetitorResourceTest` (list/create/edit access allowed for a
  `SEE_COMPETITOR_DATA`-holding super admin and rejected server-side for staff, create with
  valid data, unique-name rejection, price-entry required-source validation, price-entry
  creation stamping `created_by`, no delete action on a price entry while edit stays visible,
  create action hidden on the relation manager for a user without the right, delete action
  present on the competitor edit page for a user with the right) and 2 new
  `TenderParticipationScoreTest` cases for `winProbability()` (null when incomplete, correct
  0.0-1.0 normalization on a known fixture). 483 tests passing (up from 472 — 11 new). Pint
  clean; `phpstan --memory-limit=1G` clean on every file this task created/edited relative to
  the established `CertificateResource`-class baseline (the 5 `staticClassAccess.privateMethod`
  findings on `CompetitorResource.php` are the same finding class already present on
  `CertificateResource.php`, confirmed by running phpstan against that file directly; the
  pre-existing Pest-`$this`-typing false positives in the test files are unrelated).
- [x] **Task 3 — Tender ↔ Competitor linkage**: `App\Enums\CompetitorOutcome` (`WE_WON`,
  `THEY_WON`, `UNKNOWN`, `HasLabel`, SCREAMING_SNAKE_CASE per [[enums]]; `color()` too —
  success/danger/gray — matching `DocumentRequestStatus`'s shape). Migration
  `tender_competitors` pivot (UUID PK, `tender_id` FK `cascadeOnDelete`, `competitor_id` FK
  `cascadeOnDelete`, `outcome` string cast to enum, `known_price` nullable decimal(12,2),
  `notes` nullable text, timestamps). Model `TenderCompetitor` (`tender()`/`competitor()`
  `BelongsTo`, same "child model with `#[Fillable]`" shape as `TenderTeamMember`/
  `CompetitorPriceEntry` — deliberately not a `BelongsToMany` pivot, so the extra `outcome`/
  `known_price`/`notes` columns stay ordinary model attributes rather than pivot-attribute
  wrangling). `Tender::tenderCompetitors(): HasMany` and `Competitor::tenderCompetitors():
  HasMany` added (same relation name both sides, since both are just "this side's rows in the
  linking table" — not a shared name collision, each model only exposes its own).

  `CompetitorsRelationManager` on `TenderResource` (`relationship = 'tenderCompetitors'`,
  `getTitle()` overridden to `tender_competitors.tab_label` so the tab reads "Competitors"
  rather than the relation-name-derived default), grouped into the existing "Engagement"
  `RelationGroup` per [[filament-resources-tenders]]. Gated end-to-end on
  `Right::SEE_COMPETITOR_DATA` via a private `canManage()` (same shape as
  `PriceEntriesRelationManager`) applied to create/edit/delete — full CRUD here, unlike
  `PriceEntriesRelationManager`'s append-only shape, since a competitor sighting is a
  correctable record with no compliance requirement forcing an audit trail. Reverse read-only
  `TendersFacedRelationManager` registered on `CompetitorResource` (same `relationship =
  'tenderCompetitors'`, `getTitle()` → "Tenders faced"): no `form()`, empty
  `headerActions()`/`recordActions()` — the pivot row is only ever created/edited/deleted from
  the Tender side, this is purely a mirrored view.

  New lang files `tender_competitors.php` (tab label + field labels) and
  `competitor_outcomes.php` (enum labels); `competitors.php` gained `tenders_faced_tab` and
  `fields.tender` keys.

  Tests: `TenderResourceTest`'s new "competitors relation manager" describe block (create/edit/
  delete hidden without the right, a right-holder can record/edit/delete a sighting) and
  `CompetitorResourceTest`'s new "tenders faced relation manager" describe block (lists a
  sighting read-only, no create/edit/delete actions exist at all — `assertTableActionDoesNotExist`,
  not `assertTableActionHidden`, since the actions aren't registered rather than just gated).
  487 tests passing (up from 483 — 4 new). Pint clean; `phpstan --memory-limit=1G` clean on
  every file this task created/edited (the 5 `staticClassAccess.privateMethod` findings on
  `CompetitorResource.php` are the pre-existing task-2 baseline, unrelated to this task's
  changes).
- [x] **Task 4 — Derived analyses page**: new `App\Filament\Pages\CompetitorIntelligence`
  (`HasTable` + `InteractsWithTable`, same shape as `RolesAndPermissions` per [[pages]]'s
  permission-matrix pattern — `canAccess()` checks `Right::SEE_COMPETITOR_DATA`,
  `shouldRegisterNavigation()` mirrors it, `mount()` has the explicit `abort_unless` since
  `canAccess()` isn't auto-wired). Lives in the "Market Intelligence" nav group alongside
  `CompetitorResource`. Table over `Competitor::query()->with(['tenderCompetitors.tender.sector',
  'tenderCompetitors.tender.nutsCode'])` (eager-loaded to avoid N+1 across the computed columns)
  with 5 computed `TextColumn`s driven by `->state()` closures reading the loaded
  `tenderCompetitors` collection in memory: `encounters` (count), `wins_against_us`/
  `losses_to_us` (filtered by `CompetitorOutcome`), and `common_sector`/`common_region` (a
  private `self::mostCommon()` helper — `pluck` via closure, `filter`, `countBy`, `sortDesc`,
  first key — reused for both since the only difference is which nested attribute it reads).
  "Region" here means the linked tenders' `NutsCode.label`, not `Competitor.region` (a free-text
  field never compared against — the two aren't the same kind of data, this column answers
  "where have we encountered them," not "does their stated region match ours").

  Trap avoided: calling the private `mostCommon()` helper via `static::` (matching this file's
  other `static::` calls) trips PHPStan's `staticClassAccess.privateMethod` — switched to
  `self::` for that one call since the method is deliberately non-overridable, avoiding the
  finding instead of baselining it.

  New `lang/en/competitor_intelligence.php` (nav label, title, description, column labels);
  `competitors.php`'s existing `fields.name` key reused for the name column.

  Tests: `CompetitorIntelligencePageTest` (server-side rejection without the right; a fixture
  with 3 tender-competitor sightings across 2 sectors/regions asserting `encounters`/
  `wins_against_us`/`losses_to_us`/`common_sector`/`common_region` all resolve correctly via
  `assertTableColumnStateSet`, plus a zero-encounter competitor showing all-zero counts). 489
  tests passing (up from 487 — 2 new). Pint clean; `phpstan --memory-limit=1G` clean with zero
  findings on `CompetitorIntelligence.php`.
- [x] **Task 5 — Market analysis page**: new `App\Filament\Pages\MarketAnalysis`, plain
  (non-`HasTable`) `Page` in the "Market Intelligence" nav group — unlike task 4, this page
  isn't gated behind `Right::SEE_COMPETITOR_DATA` (it's pure tender aggregate data, no
  competitor/price fields), matching `TenderCalendar`'s precedent of no explicit `canAccess()`
  gate beyond normal panel auth. Data-level restriction still applies automatically:
  `Tender::query()` carries the `ServiceCategoryScope` global scope per [[scopes-models]], so a
  category-scoped user's breakdowns are silently limited to their own category's tenders with
  no extra code needed.

  Six breakdowns (region via `nuts_codes.label`, sector, service category, client, source,
  procurement procedure — the exact list task 5 scoped down to, trimming idea.md's fuller
  "contract size/city/duration/competitor/price level" list since those either need `SEE_PRICES`
  gating or overlap task 4/7) computed via `getViewData()` calling a private `breakdown(Closure
  $configure)` helper: each call site passes a closure doing a `join`/`leftJoin` (nullable FKs —
  `nuts_code_id`, `client_id` — use `leftJoin` so unset values still get a row) + `selectRaw('...
  as label, count(*) as total')` + `groupBy`, run against a fresh `Tender::query()`. Rendered by
  a plain Blade view (`market-analysis.blade.php`, not `HasTable`, since these are read-only
  grouped counts with no sort/search/pagination need) — one card per breakdown in a responsive
  grid, styled with the same literal Tailwind classes already proven safe under
  [[css-filament]]'s theme-scan requirement (`participation-score-summary.blade.php`'s
  established pattern). Ran `npm run build` (host machine) after adding the view, since some of
  its classes (`grid-cols-*`, `border-b`, `last:border-0`, etc.) weren't already compiled in from
  prior views.

  Trap avoided: PHPStan's `selectRaw()` literal-string requirement rejects a single generic
  helper that interpolates table/column names into the SQL string — the initial one-function,
  4-parameter version failed with `argument.type` (non-literal-string) and `property.notFound`
  (accessing `->total`/`->label` on the hydrated `Tender` model directly). Fixed by moving the
  literal SQL into 6 separate closures (one per call site, so each `selectRaw()` argument is a
  true string literal PHPStan can see) and reading values via `$row->getAttribute('label')`/
  `getAttribute('total')` instead of magic property access, which PHPStan's Larastan extension
  doesn't model for ad-hoc `selectRaw()` aliases.

  New `lang/en/market_analysis.php` (nav label, title, description, `unknown_label` for
  null-FK rows, `total_column`, one key per breakdown).

  Tests: `MarketAnalysisPageTest` (reachable by a plain STAFF user with no special right; a
  2-sector fixture asserting the sector breakdown's counts; a client-vs-no-client fixture
  asserting the unassigned tender groups under `unknown_label` rather than being dropped). 492
  tests passing (up from 489 — 3 new). Pint clean; `phpstan --memory-limit=1G` clean with zero
  findings on `MarketAnalysis.php`.

  Follow-up polish (2026-09-02, after user feedback that the plain table looked flat, then that a
  CSS-only redesign still didn't read well): went through 3 non-chart iterations (horizontal
  bar-list, KPI tiles, CSS donut+legend) before the user explicitly asked for real interactive
  charts with hover tooltips. This revisits the milestone's own "no chart widgets, reserved for
  M12" design note above — reconfirmed with the user before proceeding: M12's 3 dashboards are
  separate purpose-built pages (employee/team-lead/management cuts, global search, PDF/Excel
  export), not a redo of this breakdown page, so charting here doesn't create M12 rework.

  Implementation uses Filament's own `ChartWidget` (Chart.js, already bundled with
  `filament/widgets` — no new npm dependency). New abstract
  `App\Filament\Widgets\TenderBreakdownChartWidget` holds the shared shape: each concrete subclass
  supplies a `dimensionKey()` (for the `market_analysis.breakdowns.*` heading) and a
  `configureQuery(Builder $query): Builder` closure-equivalent (the same
  join/leftJoin+`selectRaw`+`groupBy` shape the old `MarketAnalysis::breakdown()` used); the base
  class runs it, takes the top 5 rows by count, folds anything past that into a single gray
  "Other" `market_analysis.other_label` slice (so a many-valued dimension like client doesn't
  render dozens of slivers), and returns Chart.js `doughnut` `datasets`/`labels` data with an
  8-color literal palette cycled by index. `getOptions()` returns a `RawJs` block wiring a
  Chart.js tooltip callback that computes `value` + `percentage-of-total` on hover, satisfying the
  "analytics showing on hover" ask beyond Chart.js's plain-value default. 6 concrete widgets
  (`TendersByRegionChartWidget`, `...BySectorChartWidget`, `...ByServiceCategoryChartWidget`,
  `...ByClientChartWidget`, `...BySourceChartWidget`, `...ByProcurementProcedureChartWidget`), one
  per dimension, each just the `dimensionKey()`/`configureQuery()` pair.

  `MarketAnalysis` page no longer builds `$breakdowns` itself — `getViewData()`/`breakdown()`
  were deleted, and the 6 widgets are wired via `getFooterWidgets()` (returned array of
  class-strings; Filament's base `Page::content()` schema renders these automatically inside
  `<x-filament-panels::page>`'s existing `$this->footerWidgets` slot below the page body, no
  custom Blade loop needed) with `getFooterWidgetsColumns(): int|array` returning `2` for "2 per
  row, bit big" per the user's ask — `market-analysis.blade.php` is now just the intro paragraph.
  `protected ?string $maxHeight = '300px'` on the base widget gives each chart real size at that
  2-per-row width.

  Trap avoided: `ChartWidget::getData()`/`getCachedData()` are `protected` and the chart payload
  never reaches the rendered HTML as inspectable plain text (it's JSON inside an Alpine
  `x-data` attribute via `@js()`), so `assertSee()`-style assertions on rendered output are
  fragile. Used `spatie/invade` (already a project dependency) —
  `invade(Livewire::test(Widget::class)->instance())->getData()` — to call the protected method
  directly and assert on the returned `labels`/`data` arrays, rather than screen-scraping HTML.

  Tests: rewrote `MarketAnalysisPageTest` — the page-reachability test is unchanged; the sector
  and client-breakdown tests now hit `TendersBySectorChartWidget`/`TendersByClientChartWidget`
  directly via `invade()->getData()` instead of the old `viewData('breakdowns')` access; added one
  new test asserting the top-5-plus-"Other" folding behavior (7 sectors with counts 1..7 fold the
  two smallest into a single `other_label` slice totalling 3). `phpstan --memory-limit=1G` clean
  on every new/edited file; Pint clean. Ran `npm run build` for the (unchanged) surrounding layout
  classes.
- [x] **Task 6 — Client history + auto follow-up reminders**: migration
  `add_client_reminder_columns_to_tenders_table` adds 3 nullable
  `reminder_{12,9,6}_months_sent_at` timestamp columns to `tenders` — excluded from
  `Tender`'s `#[Fillable(...)]` (forceFill-only, same as `is_archived`/`archived_at`/etc.),
  cast `datetime`.

  New `App\Enums\NotificationType::CLIENT_CONTRACT_RENEWAL` case +
  `ClientContractRenewalReminderNotification` (dual-channel per [[notifications]], same shape as
  `TenderEscalatedToManagementNotification` — `wantsEmailFor()`-gated mail, always-on database
  channel, links to the tender's view page).

  New `App\Console\Commands\CheckClientContractRenewals`
  (`tenders:check-client-renewals`), registered on the scheduler daily next to
  `certificates:check-expiry` (a 12/9/6-month cadence needs nothing hourly). Scans every
  `Tender::whereNotNull('contract_end_date')` regardless of status — explicitly not excluding
  `LOST`, per idea.md's client-history spec. Unlike `CheckCertificateExpiry`'s single
  ratchet-column threshold model, this checks the 3 reminder columns independently (each
  `null` + `monthsUntilEnd <= threshold` fires that one), since the milestone's task 1 already
  locked in 3 separate columns rather than one descending value — multiple thresholds can still
  fire together in one run after downtime (a tender first seen at 9 months out fires both the
  12- and 9-month reminders in the same run), matching the existing catch-up semantics from
  [[deadlines]]. Recipients: the tender's `owner` plus every `TEAM_LEAD`/`DEPARTMENT_HEAD` whose
  `service_category_id` matches the tender's, guarded the same way as
  `CheckDeadlineEscalations`/`CheckCertificateExpiry` against the roles not being seeded yet.

  `ClientResource` gains a `view` page (`ViewClient` + `ClientInfolist`, same
  section-plus-collapsed-meta shape as `ServiceCategoryInfolist`) and a read-only
  `TendersRelationManager` (relationship `tenders`, no header/record/toolbar actions — a
  tender's `client_id` is only ever set from `TenderResource`'s own form). Columns: internal ID,
  title, status (badge), `result.winner` (the past winner/outcome), a computed `competitors`
  column joining `tenderCompetitors.competitor.name` via `->state()` (mirrors task 4's
  in-collection approach), and contract start/end dates — eager-loads `result` and
  `tenderCompetitors.competitor` via `modifyQueryUsing()` to avoid N+1. `ClientsTable` gained a
  `ViewAction` alongside the existing `EditAction` (previously edit-only, since it had no
  relation manager until now).

  New `clients.php` keys (`tenders_tab`, `tenders.fields.*`); `notifications.php` gained the
  `client-contract-renewal` type label plus `client_contract_renewal.*` message keys.

  Tests: `CheckClientContractRenewalsTest` (12-month reminder fires once and not twice on a
  second run; nothing sent beyond 12 months out; 9-month reminder fires at that threshold; fires
  on a `LOST` tender; notifies the category's team lead/department head but not a different
  category's team lead or a same-category user without either role) and `ClientResourceTest`'s
  new "client history" describe block (a linked tender appears with its winner/competitor,
  fully read-only; a tender linked to a different client doesn't appear; the view page renders).
  500 tests passing (up from 492 — 8 new). Pint clean; `phpstan --memory-limit=1G` clean on
  every file this task created/edited.
- [x] **Task 7 — Pipeline & forecast page**: new `App\Filament\Pages\PipelineForecast`
  (`HasTable`, same shape as task 4's `CompetitorIntelligence` — no explicit `canAccess()` gate
  on the page itself, since visibility of the price-bearing columns is handled per-column
  instead, matching how `TenderForm`/`TenderInfolist` already gate `estimated_contract_volume`
  behind `Right::SEE_PRICES` rather than hiding the whole page). Query: `Tender::query()
  ->whereNotIn('status', <every TenderStatus::isTerminal() value>)->with(['participationScore',
  'teamMembers'])` (computed via a private `pipelineQuery()` reused both by `table()` and by the
  totals calculation below, rather than duplicating the terminal-status filter).

  Columns: `internal_id`/`title`/`status` (as elsewhere), `contract_start_date`,
  `estimated_contract_volume` (same "unknown" formatting as `TenderInfolist`, `->visible()`
  gated on `Right::SEE_PRICES`), `win_probability` (from task 2's
  `TenderParticipationScore::winProbability()`, rendered as a percentage or an "Incomplete"
  placeholder when the score isn't fully rated yet), `weighted_value` (volume × probability,
  null — not zero — whenever either input is unavailable; same `SEE_PRICES` gate as volume,
  since it leaks the same information), and `resource_check` (a badge counting how many of the
  5 `TeamRole` functions have at least one `TenderTeamMember` assigned — explicitly a
  best-effort staffing *signal*, not a real capacity/recruitment system, since idea.md doesn't
  specify one and none exists elsewhere in the app; green once all 5 are covered).

  Totals row: rendered in the page's own Blade view below `$this->table` (same
  custom-view-with-Tailwind-card approach as task 5's `market-analysis.blade.php`) rather than
  Filament's column-level `->summarize()`, since the total needs the same `SEE_PRICES` gate as
  the column it sums and is computed off the in-PHP `weightedValue()` helper rather than a raw
  DB aggregate. `totalWeightedPipelineValue()` re-runs `pipelineQuery()->get()->sum(...)` and
  returns `null` (hiding the card entirely) for a user without the right.

  New `lang/en/pipeline_forecast.php` (nav label, title, description, column labels, the
  "Incomplete" win-probability placeholder, the resource-check coverage string, the totals-row
  label) — reuses `tenders.fields.*` and `tenders.infolist.money_eur` for shared field labels/
  money formatting rather than duplicating them.

  Trap avoided: same PHPStan `staticClassAccess.privateMethod` issue as task 4 — the private
  `formatVolume()`/`weightedValue()`/`resourceCheckLabel()` helpers must be called via `self::`,
  not `static::`, from inside column closures.

  Tests: `PipelineForecastPageTest` (a `WON`/`LOST` tender is excluded from the table while a
  `PROCESSING` one is included; weighted value equals volume × the score's own
  `winProbability()` — computed from the fixture rather than hand-derived, since
  `contractValueRating()`'s bucketing depends on the volume used; weighted value is `null` when
  no participation score exists yet; the volume/weighted-value columns are hidden for a STAFF
  user without `SEE_PRICES` and visible for a `SEE_PRICES` holder; full 5/5 `TeamRole` coverage
  renders the expected coverage string). 506 tests passing (up from 500 — 6 new). Pint clean;
  `phpstan --memory-limit=1G` clean on every file this task created/edited. Ran `npm run build`
  after adding the totals-row view (its classes were already compiled in from prior M10 views,
  so this was a no-op rebuild, done for safety per [[css-filament]]).
- [x] **Task 8 — Demo seeding**: `DemoDataSeeder` gained 2 company-wide M10 libraries, seeded
  once in `run()` right after the existing M7 libraries (references/certificates/concept
  blocks) and before the tender loop, same one-seed-up-front pattern: `createClientLibrary()`
  (10 `Client` rows via the factory) and `createCompetitorLibrary()` (8 `Competitor` rows, each
  with 1-3 `CompetitorPriceEntry` rows attributed to a random library author, to demo
  `CompetitorResource`'s price-history tab and give the derived-analyses pages real data to
  aggregate).

  `createTender()` gained 3 things: (1) `client_id` set on ~75% of tenders (`fake()->boolean(75)`
  picking a random seeded `Client`, the remainder left `null` — deliberately, to demo
  `MarketAnalysis`'s "Unknown" grouping for the client breakdown); (2) a new
  `attachCompetitors()` call linking 0-3 random competitors per tender via
  `TenderCompetitor::factory()` with a random `CompetitorOutcome`, feeding
  `CompetitorIntelligence`'s encounter/win/loss counts and `ClientResource`'s client-history
  competitors column; (3) a new `demoClientRenewalDate()` helper that pins `contract_end_date`
  to exactly 3 tenders — one each at +11, +9, and +6 months from now — instead of the factory's
  own wide random range, spread across `(INTAKE, variant 0)`, `(PROCESSING, variant 1)`, and
  `(LOST, variant 2)` so `tenders:check-client-renewals` has real rows to fire on right after
  seeding, including one `LOST` tender per idea.md's "reminders fire on lost tenders too"
  requirement. Every other tender's `contract_end_date` is left alone (the helper returns `null`,
  meaning "don't override" — `contract_end_date` is only added to the factory's `create()` array
  when non-null, so the factory's own random range isn't clobbered for the other ~33 tenders).

  Verified via the standing throwaway-Pest-seeder-test pattern per [[database-seeders]] (written,
  run against sqlite, then deleted — never `migrate:fresh --seed` against the real dev Postgres
  DB for this kind of check): seeding `DatabaseSeeder` end-to-end produces exactly 10 clients, 8
  competitors, at least one price entry and one tender-competitor row, at least one tender with
  and one without a `client_id`, and running `tenders:check-client-renewals` immediately
  afterward fires the reminder on exactly the 3 pinned tenders (confirmed via the 3 stamped
  `reminder_*_sent_at` columns) — no errors anywhere in the full seed run. Pint clean; PHPStan
  shows the same pre-existing 21-finding baseline on `DemoDataSeeder.php` as before this task
  (confirmed via `git stash`/`git stash pop` diffing the finding count) — none of the added
  lines introduced a new finding.
- [x] **Task 9 — Docs**: `docs/15-competitors-market-intelligence.md` drafted, covering
  Clients (the optional `client_id` link on the tender form, kept alongside the required
  free-text contracting authority), client history (the read-only linked-tenders table on
  `ViewClient`) and the 12/9/6-month auto contract-renewal reminders (explicitly firing on lost
  tenders too), `Right::SEE_COMPETITOR_DATA`-gated Competitor profiles with their price-entry
  log and tender sightings, the two derived-reporting pages (Competitor Intelligence, gated;
  Market Analysis, open to all users per its category-scope-only restriction), and the Pipeline
  & Forecast page's win-probability/weighted-value/resource-check columns with their
  `Right::SEE_PRICES` gating. Audience confirmed general/public with the user (mirrors page
  14's precedent), screenshots left as placeholder comments, all sections covered evenly per
  the user's answers. Cross-linked from `03-managing-tenders.md` (new optional client field, plus
  a new "Where to go next" entry) and `08-administration.md` (new "Where to go next" entry
  pointing at the Clients row/`SEE_COMPETITOR_DATA` right covered in more depth here). Updated
  [[docs]]'s tracker checkbox list with page 15's entry.

M10 complete as of 2026-09-02. All 9 tasks shipped: Client lookup model, Competitor + price
entries, tender-competitor linkage, the Competitor Intelligence and Market Analysis derived
pages, client history + auto contract-renewal reminders, the Pipeline & Forecast page, demo
seeding, and this docs page. 507 tests passing across the milestone's own added coverage after
task 5's post-completion chart rework above (see each task's entry for the running count; the
full suite couldn't be re-run end-to-end in this environment due to a PHP memory limit
unconnected to this milestone's code — every scoped/affected test file was re-run and passes).
Flip M10's status to "Complete" in [[milestones]]'s index table. Don't build ahead into M11
without the user asking explicitly.
