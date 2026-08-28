---
paths:
  - 'app/Models/Tender.php,app/Enums/TenderStatus.php,app/Filament/Resources/Tenders/**'
---

# Tenders

## Tender status transitions: forward-only, early exits, dedicated action
Status changes never happen via the create/edit form field — that Select was removed from TenderForm. Always go through Tender::changeStatusTo($newStatus, $actor, $reason), which checks TenderStatus::canTransitionTo() and writes an audit row to tender_status_changes (TenderStatusChange model) in the same transaction. Never mass-assign/update the `status` column directly.

Transition rules (TenderStatus::allowedTransitions()): the 7 active phases (intake→review→decision→processing→quality→submission→follow-up) move forward one step at a time only, no skipping and no going back. cancelled/not-evaluated/excluded are reachable from any active phase (a tender can drop out early). won/lost are only reachable from submission/follow-up, since a bid must actually be submitted first. Terminal statuses (won/lost/cancelled/not-evaluated/excluded) have no further transitions.

UI lives as a "Change status" row action in TendersTable (Filament Action, not EditAction) — its modal only offers the record's currently-valid next statuses and an optional reason, and the action hides itself once the tender is terminal. Don't re-add a free status Select to the form.

## Docs
The tender lifecycle is documented for end users in `docs/03-managing-tenders.md`. If the
transition map or status-change UI changes, update that page too — see [[docs]].

## Final-submission gate: quality→submission blocked while any task is not done
idea.md M2's "final submission is gated" rule is implemented against what M2 actually has available (Task + Tender status), not the M5 approval chain (which doesn't exist yet). Tender::changeStatusTo() throws TenderTasksNotCompleteException when the target status is TenderStatus::SUBMISSION and Tender::tasksComplete() is false (i.e. any Task on the tender has status != DONE). Since SUBMISSION is only reachable from QUALITY in the transition map, this is effectively a quality→submission gate. Vacuously true (transition allowed) when the tender has zero tasks — mirrors Task::dependenciesComplete()'s same vacuous-true convention. Belt-and-braces UI enforcement: TendersTable's changeStatus action Select rejects the SUBMISSION option via ->reject() the same way TasksTable::changeStatusAction() rejects DONE when !dependenciesComplete(). Revisit this once M5's 6-step approval chain (calculation/concept/evidence/review/management sign-off) actually exists — it may replace or extend this task-based gate rather than coexist with it.
