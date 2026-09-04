# M6 — Bid / No-Bid Decision

Full spec: [idea.md](../../../idea.md)'s M6 section. Index: [../milestones.md](../milestones.md).

**M6 — Bid / No-Bid Decision is complete**, started 2026-08-30 and finished 2026-09-01 at the
user's explicit request, built incrementally task-by-task (same rhythm as M3–M5).

Four design decisions were confirmed with the user before any code was written:
- **Missing score inputs are manual entry fields.** idea.md lists 10 participation-score
  factors; only contract value (`Tender.estimated_contract_volume`) and expected margin
  (`TenderCalculation.actual_margin` via [[calculations]]) are already captured elsewhere.
  Past win rate has no source until M9/M10 exist, so it's fixed at a neutral rating and
  flagged as unknown rather than guessed. The remaining 7 factors (distance, staffing
  requirement, wage/qualification requirements, reference position, competitive intensity,
  contractual penalties, strategic value) get dedicated manual rating fields on a new
  `TenderParticipationScore` model, rather than deferring them or leaving them out.
- **Score formula: 10 factors, each rated 1-5, equal weight, summed / 50 × 100 = 0-100 score.**
  No per-factor weighting — idea.md gives no weighting guidance, so equal weight is the
  simplest defensible default (not built as admin-configurable; that's speculative scope this
  milestone doesn't need). Derived factors are bucketed into the same 1-5 scale:
  - **Contract value** (from `Tender.estimated_contract_volume`, EUR): <50k=1, 50k–150k=2,
    150k–400k=3, 400k–1M=4, >1M=5.
  - **Expected margin** (from `currentCalculation()->actual_margin`, %): no calculation or
    margin ≤0%=1, 0–5%=2, 5–10%=3, 10–20%=4, >20%=5.
  - **Past win rate**: fixed at 3 (neutral), always rendered with an "unknown — no result
    history yet" note, not silently averaged in as if it were a real measurement.
  The score is **computed on demand, not persisted** — a stored column would go stale the
  moment `estimated_contract_volume` or the calculation's `actual_margin` changes after the
  ratings were entered. `TenderParticipationScore::score(): ?int` returns `null` ("incomplete")
  until all 7 manual ratings are set, otherwise runs the bucket/weight math live.
- **Bid/no-bid decision is an append-only log, not a mutable field.** A new
  `TenderBidDecision` model — one row per recorded decision (`BID`/`NO_BID` via a new
  `App\Enums\BidDecision`, mandatory `reason` when `NO_BID`, `decided_by`, `decided_at`, and a
  frozen `score` snapshot captured at decision time so the historical record survives later
  score changes). Rows are never updated or deleted; `Tender::currentBidDecision(): HasOne`
  (explicit `orderByDesc('decided_at')->limit(1)`, not `ofMany()` per [[models]]'s UUID-PK
  trap) surfaces the latest one. Editing a decision later means recording a new row, per
  idea.md's explicit "edits create a new logged entry, not a silent overwrite."
- **New `Right::MAKE_BID_DECISION`**, not a reuse of an existing right or a role check —
  consistent with M1's rights model (independent of role, individually assignable). Gates both
  editing the participation-score ratings and recording a decision; viewing the score/decision
  history stays open to anyone with tender access, mirroring
  [[calculations]]'s `CalculationsRelationManager` tab-visible-but-actions-gated pattern.
- **Informational only — no status-transition gating.** The score and decision are recorded
  and displayed but do not block `decision→processing` or any other `Tender::changeStatusTo()`
  transition. idea.md is explicit that "the system never decides automatically"; gating the
  transition would make the tool coercive in a way the spec doesn't ask for. (Rejected: gating
  `decision→processing` on a `BID` decision existing, mirroring M5's submission gate — would
  contradict the human-in-the-loop framing and wasn't requested.)

Planned tasks for M6:
- [x] `App\Enums\BidDecision` (`BID`/`NO_BID`, values `'bid'`/`'no-bid'`, `HasLabel` via a new
  `bid_decisions.*` lang file — mirrors `CalculationApprovalStep`'s shape) +
  `Right::MAKE_BID_DECISION` case added to the existing `Right` enum (`'make-bid-decision'`),
  with a `rights.*` lang key and seeded into `RolesAndPermissionsSeeder::DEFAULT_ROLE_RIGHTS`
  alongside the other default rights. Default grants: super-admin and department-head get it
  (mirroring their all-rights default), team-lead gets it too (decision-making authority
  consistent with team-lead already holding `EXECUTE_FINAL_SUBMISSION`), calculation and staff
  do not. This is a product default the user can adjust per-role afterward via the
  `RolesAndPermissions` admin page, same as every other right — not a hard rule. No `HasColor`
  yet; the won/lost-style badge colours belong to the relation-manager table task, not the enum.
  Tests: `BidDecisionTest`/`RightTest` (label resolution, generic loop over cases) and
  `RolesAndPermissionsSeederTest` (updated to assert calculation/staff don't get the new right,
  added a team-lead-specific assertion) — all passing. No dedicated "rejected without the
  right" action test yet since no gated action exists until the UI task.
- [x] `TenderParticipationScore` model + migration: UUID PK, `tender_id` FK unique (one row per
  tender, `cascadeOnDelete`), 7 nullable `unsignedTinyInteger` rating columns
  (`MANUAL_RATING_FIELDS` constant lists them — `distance_rating`,
  `staffing_requirement_rating`, `wage_qualification_rating`, `reference_position_rating`,
  `competitive_intensity_rating`, `contractual_penalties_rating`, `strategic_value_rating`,
  1-5 range enforced in the form later, not yet at the DB level), timestamps.
  `Tender::participationScore(): HasOne` added next to `currentCalculation()`.
  `TenderParticipationScore::score(): ?int` implements the bucket/weight formula: null until
  all 7 manual ratings are set, otherwise `(sum(7 manual) + contractValueRating() +
  marginRating() + 3) / 50 × 100`, rounded. `contractValueRating()`/`marginRating()` are public
  (not private — the UI task's read-only summary needs to display each derived factor's current
  bucket individually, not just the total) with `private static bucketContractValue()`/
  `bucketMargin()` doing the actual threshold match; `marginRating()` reads
  `$this->tender->currentCalculation()->first()?->actual_margin` (method-call form, matching
  the existing convention in `Tender::changeStatusTo()`/`TendersTable`, not property access).
  `estimated_contract_volume_unknown === true` is treated identically to a null volume (lowest
  bucket), not read as a real value. `pastWinRateRating()` exposes the fixed 3 for the UI.
  Factory has a `rated(int $rating = 3)` state filling all 7 manual fields. Tests
  (`TenderParticipationScoreTest`, 28 assertions): score null until complete; full score math
  for a low-end and high-end case; every contract-value and margin bucket edge (via `->with()`
  datasets); unknown-flag override; multi-version calculation picks the latest; unique
  `tender_id` constraint; cascade-delete with its tender. `phpstan analyse --memory-limit=1G`
  clean (the container's default 128M isn't enough for a single-file run — pass a higher limit
  when running phpstan ad hoc, unrelated to this app's code).
- [x] `TenderBidDecision` model + migration: UUID PK, `tender_id` FK (`cascadeOnDelete`),
  `decision` enum-cast `BidDecision`, `reason` nullable text, `score` nullable
  `unsignedTinyInteger` (frozen snapshot, 0-100), `decided_by` FK users (`restrictOnDelete`,
  matching `TenderStatusChange`/`TenderCalculation`'s actor-FK convention), `decided_at`
  datetime, no `updated_at` (`$timestamps = false`, mirroring `TenderStatusChange` exactly).
  Model-level guard: a new `App\Exceptions\BidDecisionReasonRequiredException` (mirrors
  `TenderCalculationNotApprovedException`'s `::make()` static-constructor shape, extends
  `InvalidArgumentException` rather than `RuntimeException` since it's a validation failure, not
  a state-machine one) thrown from a `static::creating()` hook in `booted()` when
  `decision === BidDecision::NO_BID && reason === null` — enforced at the model layer so it
  fires regardless of entry point (seeder, tinker, form); the Filament form's mirrored
  `->required()` rule is deferred to the UI task. `Tender::bidDecisions(): HasMany`
  (`orderByDesc('decided_at')`) and `Tender::currentBidDecision(): HasOne` (explicit
  `orderByDesc()->limit(1)`, not `ofMany()`, per [[models]]'s UUID-PK trap) added next to
  `participationScore()`. Factory defaults to `BID` with a null reason (so it never randomly
  throws) plus a `noBid()` state that sets `NO_BID` with a fake sentence reason. Tests
  (`TenderBidDecisionTest`, 9 assertions): `NO_BID` without a reason throws
  `BidDecisionReasonRequiredException`; `NO_BID` with a reason succeeds; `BID` without a reason
  succeeds; decisions accumulate as separate rows rather than overwriting;
  `bidDecisions()`/`currentBidDecision()` ordering; `currentBidDecision()` is null with no rows;
  cascade-deletes with its tender. Pint and `phpstan --memory-limit=1G` clean, full suite
  (334 tests) green.
- [x] Filament UI: `BidDecisionRelationManager` registered on `TenderResource` (5th relation
  manager). Table is the append-only decision history (decision badge via new
  `BidDecision::color()` — green for `BID`, red for `NO_BID`, mirroring `TenderStatus::color()`'s
  exact shape — score, reason, decided by, decided at), `->recordActions([])` since rows are
  immutable. Header actions: "Edit score inputs" (`updateOrCreate` on the tender's
  `participationScore()` HasOne, 7 rating `Select`s built from `MANUAL_RATING_FIELDS`) and
  "Record decision" (decision `Select` + `reason` `Textarea` required when `NO_BID`, snapshotting
  `currentOrNewScore()->score()` into the row at submit time) — both gated by
  `Right::MAKE_BID_DECISION` via `->visible()` + `->before(fn () => abort_unless(...))`. The
  read-only summary (computed score or "incomplete — N of 7 ratings missing", every factor
  including the derived/fixed ones) is a Blade partial
  (`resources/views/filament/relation-managers/participation-score-summary.blade.php`) injected
  via a `content()` override — `$table->header()` was tried first and rejected: it replaces
  Filament's entire header slot, which also renders `headerActions()`, so it would have silently
  dropped both header buttons. Instead `content()` inserts a `Filament\Schemas\Components\View`
  component (`->viewData(...)`) before `EmbeddedTable::make()`, alongside the untouched
  `RenderHook`/tabs components from the parent implementation — this is the reusable pattern for
  any future "summary above the table" need. `TenderParticipationScore::currentOrNewScore()`-style
  helper (`currentOrNewScore()` on the RelationManager) returns an unsaved instance with
  `setRelation('tender', $tender)` when no row exists yet, so `contractValueRating()`/
  `marginRating()`/`score()` resolve correctly even pre-first-save. New lang files
  `tender_participation_scores.php` (factor labels, summary strings) and
  `tender_bid_decisions.php` (field/action labels). `npm run build` run for the new Blade
  partial's Tailwind classes, per [[css-filament]].
  Hit and fixed a real bug during testing: the conditional `reason` requirement used
  `$get('decision') === BidDecision::NO_BID->value`, which never matched because `$get()` returns
  the hydrated enum *instance* (not its raw value) once the sibling `Select` (backed by
  `->options(BidDecision::class)`) has a value — recorded as a durable rule in
  [[resources]] since it'll bite the next enum-backed conditional-required field too. Also
  discovered mid-task that `callTableAction(...)->assertForbidden()` cannot test a
  `->before(abort_unless(...))` guard on an action that is *also* `->visible()`-gated to false for
  that user — Filament's own test harness refuses to mount an invisible action before your
  assertion even runs. Also recorded in [[resources]]; the "hides both actions from a user
  without the right" `assertTableActionHidden` test is the correct (and sufficient) server-side
  denial coverage for this action shape, so the two flawed forced-call tests were dropped rather
  than kept broken.
  Tests (`TenderResourceTest.php`'s new "bid decision relation manager" describe block, 8 tests):
  lists only the tender's own decisions; hides both actions without the right; incomplete-summary
  and computed-score rendering; editing ratings with the right (and the DB row reflects it);
  recording a `BID` decision without a reason; requiring a reason for `NO_BID` (server-side
  validation, not just client hint). Full suite (342 tests), Pint, and
  `phpstan --memory-limit=1G` all clean.
- [x] Docs: new standalone page `docs/11-bid-no-bid-decision.md` (the user chose a new page over
  appending to `10-calculations-approvals.md`, mirroring how 09/10 were added alongside their
  milestones) covering the ten participation-score factors and their manual/derived/fixed
  split, entering the seven manual ratings, the score being computed live rather than stored,
  recording a decision with the mandatory reason on decline, the frozen score snapshot, the
  append-only history, the `MAKE_BID_DECISION`-right-vs-general-viewing split, and that the
  decision doesn't gate status transitions. Cross-linked from `03-managing-tenders.md`,
  `08-administration.md`, and `10-calculations-approvals.md`; all three timestamps bumped to
  01/09/2026 per [[docs]]'s sync rule. See [[docs]] for full detail.

M6 UI/data follow-ups after the task list above was closed out (2026-09-01, same session):
- **Form layout**: `BidDecisionRelationManager`'s two header-action forms redone to match
  [[resources]]'s `Section`/`Grid`/`prefixIcon` convention instead of one bare full-width
  `Select` per line. "Edit score inputs"' 7 rating `Select`s now sit in a `Grid::make(2)`, each
  with a `prefixIcon` matching its factor (`OutlinedMapPin` distance, `OutlinedUserGroup`
  staffing, `OutlinedAcademicCap` wage/qualification, `OutlinedTrophy` reference position,
  `OutlinedFire` competitive intensity, `OutlinedScale` contractual penalties, `OutlinedStar`
  strategic value — new `RATING_FIELD_ICONS` const map). "Record decision"'s `decision` `Select`
  got `prefixIcon(OutlinedCheckBadge)` and sits in a `Grid::make(2)` next to the `reason`
  `Textarea` (`Textarea` has no `prefixIcon` — `HasAffixes` isn't in its trait list — so it keeps
  `->columnSpanFull()` instead). No lang or behavior changes; all 8 relation-manager tests still
  pass unchanged.
- **Demo data**: `DemoDataSeeder::createTender()` now seeds a `TenderParticipationScore` (+
  `TenderBidDecision` for 2 of every 3 variants) per tender via a new `createBidDecision()`
  helper, called after the calculation block (so the expected-margin factor reads a real
  calculation) and before `advanceTender()`. Variant 0 gets a complete score + `BID`; variant 1
  gets a complete score + `NO_BID` with a reason; variant 2 is left with only 3 of 7 manual
  ratings and no decision at all, to demo both the "incomplete" summary and the empty
  decision-history state. `decided_by` prefers a team member holding `MAKE_BID_DECISION` over
  the tender owner, since the owner isn't guaranteed to hold it. Verified via a throwaway Pest
  test invoking `DemoDataSeeder` against the sqlite testing DB (per [[database-seeders]]'s
  established pattern — never run `migrate:fresh --seed` against the real dev Postgres DB for
  this), then deleted; full suite (342 tests), Pint, and `phpstan --memory-limit=1G` all clean.

M6 is additive: it introduces one new right, two new tables, and one new tender-detail tab; it
does not touch `Tender::changeStatusTo()`, the M5 approval chain, or any existing transition
guard.
