# M9 — Result & Lessons Learned

Full spec: [idea.md](../../../idea.md)'s M9 section. Index: [../milestones.md](../milestones.md).

**M9 — Result & Lessons Learned is now in progress**, started 2026-09-02.

Design decisions confirmed with the user before any code was written:
- **Win/loss reasons are multi-select**, not a single primary reason — idea.md's 12-category
  list ("categorized by: price, quality, concept, ...") has no "pick one" language, and a loss
  is often multi-causal in practice.
- **The 3 mandatory lessons-learned questions use a standard retrospective set**: "What went
  well?", "What would we do differently next time?", "What should we change in our
  process/approach going forward?" — generic, not tender-type-specific.
- **Result record and Lessons Learned only become creatable once the tender is terminal**
  (`TenderStatus::isTerminal()` — won/lost/cancelled/not-evaluated/excluded), matching the
  "close the loop on every closed tender" framing. No new status-transition gate is added to
  `Tender::changeStatusTo()` itself — this is a Create-action visibility gate on the two new
  relation managers only, same shape as every other permission-based `->visible()` check in
  this app, not a workflow gate like the quality→submission calculation-approval gate.
- **`award_decision` is free text**, capturing the client's own stated rationale for their
  award (e.g. quoted from the official award notice) — distinct from `reasoning`, which is our
  own internal analysis. Neither duplicates `Tender.status` (won/lost/cancelled/not-evaluated/
  excluded), which already records the procedural outcome.

Additional decisions made while scoping (not asked, low-stakes implementation choices):
- **Win/loss analysis is folded into the Result record**, not a separate model/tab — it's a
  `win_loss_reasons` array column (jsonb, cast `array`, values from a new `App\Enums\
  WinLossReason` with the 12 idea.md cases) on `TenderResult` alongside the outcome fields,
  since idea.md presents it as an analysis *of* the result rather than an independent
  sub-workflow (unlike M8's Document Requests, which idea.md explicitly calls "its own
  sub-workflow"). Rendered via a `Select::make('win_loss_reasons')->multiple()->
  options(WinLossReason::class)` — first multi-value-enum field in this codebase; no
  precedent existed to follow (checked: no `AsEnumCollection`/multi-select-enum pattern
  anywhere else), so a plain `array` cast + enum-backed options is the simplest correct choice.
- **`TenderResult` and `TenderLessonsLearned` are HasOne singletons** (unique `tender_id` FK),
  exposed through ordinary RelationManager tables the same way M8's `SubmissionRelationManager`/
  `FollowUpRelationManager` are — `CreateAction` hidden once a record exists (plus, per the
  gating decision above, hidden unless the tender is terminal), no new "singleton child"
  abstraction.
- **`price_gap` is server-computed, never a form input.** `winning_price − our_price` when both
  are present, else `null` — computed in the RelationManager's `mutateDataUsing` on both create
  and edit (mirrors `TenderSubmission`'s server-stamped `receipt_confirmed_at`), stored as its
  own column (not computed on every read) so it survives independently if one of the two prices
  is later cleared. Displayed as a disabled `Placeholder` in the form (visible only once a
  record exists) and as its own table column — never an editable field.
- **`winning_price`/`our_price`/`price_gap` are gated behind `Right::SEE_PRICES`** exactly like
  `TenderCalculation`'s cost/margin fields ([[calculations]]) — `->visible(fn () =>
  auth()->user()?->can(Right::SEE_PRICES->value) ?? false)` on each of the three fields, in the
  form (which doubles as the `ViewAction` display, no dedicated infolist needed since there are
  no file attachments to gallery-render) and on their table columns. No new `Right` case.
- **`winner` stays a plain nullable string**, not a new lookup/relation to a `Competitor`
  entity — that model doesn't exist until M10, and idea.md's M13 note says not to make
  first-class structured-data choices in earlier milestones that would block a later bolt-on,
  not that every string must already anticipate the exact future FK. A free-text column is
  trivially migratable to a FK later (M10 can backfill `winner_competitor_id` then); building a
  `Competitor` table now would be scope creep into M10's own explicitly-not-started work.
- **No file-attachment model for "supporting documents".** Same reasoning as M8's Communication
  entries: idea.md's M4 already seeded `DocumentCategory::RESULT`/`POST_ANALYSIS` specifically
  anticipating this milestone ([[documents]], [[milestones]]'s m4 file) — a second, parallel
  attachment mechanism on `TenderResult` would duplicate the existing Documents tab. The
  Result tab's UI points users at the Documents tab's `RESULT`/`POST_ANALYSIS` categories.
- **Lessons Learned's 3 answers are individually required (non-nullable `text` columns)**, so
  `EditAction`'s own required-field validation is what prevents "editable away" per idea.md's
  "retained permanently ... not editable away later" — a correction/typo-fix is still allowed
  (an `EditAction` exists, gated the same `canManage()` way as every other relation manager),
  but blanking an answer is rejected by ordinary required-field validation, not a bespoke
  immutability mechanism. No delete action at all is wired (same append-only philosophy as
  `CommunicationRelationManager`/`DocumentRequestsRelationManager`'s no-delete pattern).
- **Write access on both new relation managers follows the standing `linkedToDocuments()` /
  `TenderForm::canManageTeam()` pattern** ([[resources-tenders]], [[scopes-models]]) — same as
  every M7/M8 relation manager, no new `Right` case introduced for gating who can create/edit
  a result or lessons-learned record (only the three price *fields* are `SEE_PRICES`-gated,
  independently of who can edit the record as a whole).

Planned tasks for M9:
- [x] Enum + lang scaffolding: `App\Enums\WinLossReason` (`HasLabel`, 12 cases in
  idea.md's listed order: `PRICE`/`QUALITY`/`CONCEPT`/`REFERENCES`/`EXPERIENCE`/`STAFFING`/
  `FORMAL_ERROR`/`EXCLUSION`/`CAPACITY`/`CONTRACT_TERMS`/`COMPETITOR`/`STRATEGIC_DECISION`,
  backed values lowercase-kebab-case per [[enums]]). New lang file `win_loss_reasons.php`.
  Test: `tests/Feature/Enums/WinLossReasonTest.php` (label-resolution loop over all cases,
  mirrors `CommunicationTypeTest`). 449 tests passing (up from 448), Pint clean.
- [x] Result record: migration `tender_results` (UUID PK, `tender_id` FK unique
  `cascadeOnDelete`, `winner` nullable string, `our_rank` nullable integer, `winning_price`
  nullable decimal(12,2), `our_price` nullable decimal(12,2), `price_gap` nullable
  decimal(12,2), `award_date` nullable date, `known_evaluation` nullable text, `reasoning`
  nullable text, `award_decision` nullable text, `win_loss_reasons` nullable jsonb,
  `created_by` FK users `restrictOnDelete`, timestamps). Model `TenderResult` (casts
  `award_date` to date, `win_loss_reasons` to `array`, decimal columns left as Eloquent's
  default decimal-cast per this app's existing convention on `TenderCalculation`'s output
  columns; `Tender::result(): HasOne`). `ResultRelationManager`: form fields for every column
  except `price_gap` (computed) — `winner`/`our_rank`/`award_date`/`known_evaluation`/
  `reasoning`/`award_decision` always visible, `winning_price`/`our_price` gated
  `Right::SEE_PRICES`, `win_loss_reasons` a `Select::make()->multiple()->
  options(WinLossReason::class)`; a disabled `Placeholder` for `price_gap` shown only once a
  record exists (also `SEE_PRICES`-gated). `CreateAction` visible only when `canManage() &&
  $tender->status->isTerminal() && $tender->result === null` (helper `canCreateResult()` —
  named to avoid colliding with `RelationManager`'s own protected `canCreate()`, same trap
  `CommunicationRelationManager::canEditCommunication()` sidesteps for `canEdit()`); both
  create and edit mutate hooks recompute `price_gap` server-side from the submitted prices via
  a shared `computePriceGap()` helper. Table columns: `winner`, `our_rank`, `award_date`,
  price columns (`SEE_PRICES`-gated via `->visible()`, still asserted with
  `assertTableColumnHidden` in tests since a `->visible()`-gated column still *exists* on the
  table, mirrors `CalculationsRelationManager`'s own test pattern), `win_loss_reasons` (badge
  list via `formatStateUsing`). New lang file `tender_results.php`. Registered on
  `TenderResource::getRelations()` right after `DocumentRequestsRelationManager`. Tests added
  to `TenderResourceTest.php`: a "result relation manager" describe block (create hidden for a
  non-terminal tender even for a manager, create hidden for an unlinked user on a terminal
  tender, create stamping `created_by`, create hidden once a result already exists, price
  fields/columns hidden without `SEE_PRICES`, `price_gap` computed correctly when both prices
  present and left `null` when one is missing, manager-can-edit while unrelated, edit hidden
  for an unlinked user). 457 tests passing (up from 449 — 8 new), one pre-existing unrelated
  flaky faker-phone test in this same file reconfirmed via isolated re-run (documented in
  M8's own build history). Pint clean; `phpstan --memory-limit=1G` clean on every file this
  task created/edited (the pre-existing Pest-`$this`-typing false positives already present
  throughout `TenderResourceTest.php` are unrelated to this task's added lines, confirmed by
  checking the specific line numbers this task added).
- [x] Lessons learned: migration `tender_lessons_learned` (UUID PK, `tender_id` FK unique
  `cascadeOnDelete`, `went_well` text NOT NULL, `differently_next_time` text NOT NULL,
  `process_changes` text NOT NULL, `created_by` FK users `restrictOnDelete`, timestamps).
  Model `TenderLessonsLearned` (`Tender::lessonsLearned(): HasOne`; needed an explicit
  `protected $table = 'tender_lessons_learned'` — Eloquent's default pluralization of
  `TenderLessonsLearned` guesses `tender_lessons_learneds`, since "learned" doesn't pluralize
  the way Laravel's inflector expects; caught immediately by the first test run against sqlite,
  "no such table: tender_lessons_learneds"). `LessonsLearnedRelationManager` mirrors
  `ResultRelationManager`'s gating (`canManage() && $tender->status->isTerminal() &&
  $tender->lessonsLearned === null` for `CreateAction` visibility, helper named
  `canCreateLessonsLearned()` for the same `RelationManager::canCreate()` collision reason as
  `ResultRelationManager::canCreateResult()`), all 3 fields `->required()` on both create and
  edit (no bespoke immutability logic beyond that), no delete action wired at all. New lang
  file `tender_lessons_learned.php` (the 3 field labels double as the actual standard
  retrospective question text: "What went well?" / "What would we do differently next time?" /
  "What should we change in our process/approach going forward?"). Registered on
  `TenderResource::getRelations()` right after `ResultRelationManager`. Tests added to
  `TenderResourceTest.php`: a "lessons learned relation manager" describe block (create hidden
  for a non-terminal tender, create hidden for an unlinked user, create stamping `created_by`,
  create hidden once a record already exists, edit rejects a blanked-out answer via required
  validation, manager-can-edit while unrelated, edit hidden for an unlinked user). 464 tests
  passing (up from 457 — 7 new). Pint and `phpstan --memory-limit=1G` clean on every file this
  task created/edited.
- [x] Demo seeding: `DemoDataSeeder::createTender()` gains `createResult()`/
  `createLessonsLearned()`, gated on `$status->isTerminal()` (called right after the
  `$reachesFollowUp` block, same after-`advanceTender()` placement as `createSubmission()`/
  `createFollowUp()`). `winner`/`our_rank`/`winning_price`/`win_loss_reasons` vary by outcome:
  null/empty on `won` (`our_rank` 1, `winning_price` mirrors `our_price` since we are the
  winner) and on `cancelled` (procedure never concluded, `our_price` also left `null`);
  populated on `lost`/`excluded`/`not-evaluated` (`$othersWon`, `our_rank` 2-5, `winner` a fake
  company, 1-3 random `WinLossReason` values). `price_gap` computed with the same formula as
  `ResultRelationManager::computePriceGap()`. `createLessonsLearned()` is a flat 3-paragraph
  fake fill, no outcome branching needed since idea.md's 3 questions apply identically
  regardless of win/loss. Verified via the standing throwaway-Pest-test pattern — seeded
  `DatabaseSeeder::class` (not `DemoDataSeeder::class` directly, since it needs
  `RolesAndPermissionsSeeder` to run first or `assignRole()`/`givePermissionTo()` inside
  `DemoDataSeeder` throws `RoleDoesNotExist`) against sqlite, asserted every terminal-status
  tender has both a `result` and a `lessonsLearned` row and every non-terminal tender has
  neither; passed, then deleted, not committed (never `migrate:fresh --seed` against the real
  dev Postgres DB to check this per [[database-seeders]]). Full suite 464/464 passing (no
  regressions — seeding logic only, no schema/test changes in this task). Pint clean;
  `phpstan --memory-limit=1G` clean relative to the file's existing baseline (confirmed the 21
  pre-existing findings in `DemoDataSeeder.php` all sit outside the lines this task added).
- [ ] Docs: `docs/14-result-lessons-learned.md` (next slot after M8's `docs/13-...`, per
  [[docs]]'s tracker) — covers the Result tab's fields including the `SEE_PRICES` gate on
  price fields and the auto-computed price gap, the multi-select win/loss categorization, the
  Lessons Learned tab's 3 fixed questions and its permanently-retained/no-delete framing, and
  that both tabs only unlock once the tender reaches a terminal status. Ask the user
  clarifying questions specific to this page (audience, screenshots) before drafting, per
  [[docs]]'s standing rule. Cross-linked from `03-managing-tenders.md` (terminal statuses) and
  `09-tender-documents.md` (distinguishing this structured record from the `RESULT`/
  `POST_ANALYSIS` document categories). Update `.ai/rules/docs.md`'s tracker checkbox list.
