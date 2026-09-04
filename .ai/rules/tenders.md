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

## Final-submission gate: quality→submission blocked until the calculation approval chain is complete
idea.md M2's "final submission is gated" rule was originally implemented against Task completion (Tender::tasksComplete()), a stand-in for what M5's formal approval chain does properly. Now that M5 exists, Tender::changeStatusTo() throws TenderCalculationNotApprovedException when the target status is TenderStatus::SUBMISSION and the tender's currentCalculation() is either absent or not fully approved — i.e. `$this->currentCalculation()->first()?->isFullyApproved() ?? false` is false. isFullyApproved() checks all 6 CalculationApprovalStep cases have an approved TenderCalculationApproval row (see [[calculations]], or m5-calculation-approvals.md's task-4 entry). Since SUBMISSION is only reachable from QUALITY in the transition map, this is effectively a quality→submission gate. Task::tasksComplete() itself is unchanged and still callable, but is no longer consulted by changeStatusTo() — it's informational task-tracking only now, per the M5 design decision recorded in m5-calculation-approvals.md. TenderTasksNotCompleteException was deleted (dead code once nothing threw it). Belt-and-braces UI enforcement: TendersTable's changeStatus action Select rejects the SUBMISSION option via ->reject() using the same currentCalculation()->isFullyApproved() check, mirroring TasksTable::changeStatusAction()'s rejection of DONE when !dependenciesComplete().
