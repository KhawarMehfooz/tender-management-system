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
- [ ] **Task 2 — Competitor model + price entries**: migration `competitors` (UUID PK, `name`
  string, `region`/`service_areas`/`known_clients`/`strengths`/`weaknesses`/`market_segments`/
  `internal_notes` nullable text/string per field list above, timestamps). Model `Competitor`.
  `CompetitorResource` gated `Right::SEE_COMPETITOR_DATA`. Migration `competitor_price_entries`
  (UUID PK, `competitor_id` FK `cascadeOnDelete`, `price` decimal(12,2), `source` string NOT
  NULL, `observed_on` date nullable, `context` nullable text, `created_by` FK users
  `restrictOnDelete`, timestamps). Model `CompetitorPriceEntry`.
  `PriceEntriesRelationManager` on `CompetitorResource` (append-only, no delete, same pattern
  as M8/M9's audit-trail relation managers). Helper on `TenderParticipationScore` (or a new
  small service) to normalize the existing score into a 0-1 win-probability figure for task 7
  to reuse. New lang files `competitors.php`, `competitor_price_entries.php`. Tests:
  `CompetitorResourceTest` (CRUD, `SEE_COMPETITOR_DATA` gating on nav + price fields), price
  entry required-source validation.
- [ ] **Task 3 — Tender ↔ Competitor linkage**: new `App\Enums\CompetitorOutcome` (`WE_WON`,
  `THEY_WON`, `UNKNOWN`, `HasLabel`, SCREAMING_SNAKE_CASE per [[enums]]). Migration
  `tender_competitors` pivot (UUID PK, `tender_id` FK `cascadeOnDelete`, `competitor_id` FK
  `cascadeOnDelete`, `outcome` string cast to enum, `known_price` nullable decimal(12,2),
  `notes` nullable text, timestamps). `CompetitorsRelationManager` on `TenderResource`
  (grouped into the "Engagement" `RelationGroup` per [[filament-resources-tenders]]), gated
  `Right::SEE_COMPETITOR_DATA`. Reverse read-only "Tenders faced" table on
  `CompetitorResource`'s view page. Tests added to `TenderResourceTest`/`CompetitorResourceTest`.
- [ ] **Task 4 — Derived analyses page**: new Filament `Page` (`HasTable`), gated
  `Right::SEE_COMPETITOR_DATA` with explicit `canAccess()`/mount-time `abort_unless` per
  [[pages]]. Table over `Competitor::query()` with computed columns (encounter count from
  `tender_competitors`, wins-against-us, losses-to-us, most-common region/sector overlap).
  Tests: a feature test asserting the table renders correct aggregate numbers for a known
  fixture set.
- [ ] **Task 5 — Market analysis page**: new Filament `Page` with several small `groupBy()`
  breakdown tables (region, sector, service category, client, source, procurement procedure)
  over `Tender::query()`, respecting the existing `ServiceCategoryScope` (category-scoped users
  see only their own category's breakdown, matching every other tender-derived view in this
  app). Tests for at least 2-3 of the breakdown queries against a known fixture set.
- [ ] **Task 6 — Client history + auto follow-up reminders**: `ClientResource` gains a
  `ViewClient` page (infolist + read-only tenders table scoped to `client_id`, showing status,
  outcome via `TenderResult.winner`, competitors via `tender_competitors`). Migration adds the
  3 `reminder_*_sent_at` nullable timestamp columns to `tenders`. New
  `App\Enums\NotificationType::CLIENT_CONTRACT_RENEWAL` case +
  `ClientContractRenewalReminderNotification` (dual-channel per [[notifications]]). New Artisan
  command (`tenders:check-client-renewals` or similar), registered on the scheduler next to
  `tenders:check-deadline-escalations`, scanning tenders with a `contract_end_date` 12/9/6
  months out (any status, including lost per idea.md), notifying owner + category
  TEAM_LEAD/DEPARTMENT_HEAD once per threshold, stamping the matching `reminder_*_sent_at`
  column so it never re-fires. Tests: command test with time-travel fixtures for each of the 3
  thresholds, a "doesn't re-notify once stamped" test, a "fires on a lost tender too" test.
- [ ] **Task 7 — Pipeline & forecast page**: new Filament `Page` (`HasTable`) over
  `Tender::query()->whereNotNull(...)->where('status', not terminal)`, columns for
  `estimated_contract_volume` (SEE_PRICES-gated), normalized win probability (task 2's
  helper), weighted value, `contract_start_date`, a best-effort resource-check flag, plus a
  totals row for summed weighted pipeline value. Tests covering the weighted-value calculation
  and the terminal-status exclusion.
- [ ] **Task 8 — Demo seeding**: `DemoDataSeeder` gains client backfill/assignment, competitor +
  price-entry + tender-competitor seeding (varied outcomes), and populates enough
  `contract_end_date` values landing inside the 12/9/6-month windows to demo the reminder
  command firing (verified via the standing throwaway-Pest-seeder-test pattern per
  [[database-seeders]], never `migrate:fresh --seed` against the real dev DB for this check).
- [ ] **Task 9 — Docs**: `docs/15-competitors-market-intelligence.md` (next slot per
  [[docs]]'s tracker) covering Clients, Competitors + price entries, the tender-competitor
  link, the derived analyses/market analysis pages, client history, the auto-renewal reminder
  timing, and the pipeline/forecast page — ask the user the standard audience/screenshot
  clarifying questions first per [[docs]]. Cross-link from `03-managing-tenders.md` (new
  optional client field) and `08-administration.md` (new Client lookup table). Update
  [[docs]]'s tracker checkbox list.

Execute one task at a time, confirming with the user before moving to the next task (same
rhythm as M2–M9). Once all 9 tasks are checked, add a completion line here and flip M10's
status to "Complete" in [[milestones]]'s index table — don't build ahead into M11 without the
user asking explicitly.
