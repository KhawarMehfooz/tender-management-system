---
paths:
  - 'app/Models/Tender.php,app/Enums/TenderStatus.php,app/Filament/Resources/Tenders/**'
---

# Tenders

## Tender status transitions: forward-only, early exits, dedicated action
Status changes never happen via the create/edit form field — that Select was removed from TenderForm. Always go through Tender::changeStatusTo($newStatus, $actor, $reason), which checks TenderStatus::canTransitionTo() and writes an audit row to tender_status_changes (TenderStatusChange model) in the same transaction. Never mass-assign/update the `status` column directly.

Transition rules (TenderStatus::allowedTransitions()): the 7 active phases (intake→review→decision→processing→quality→submission→follow-up) move forward one step at a time only, no skipping and no going back. cancelled/not-evaluated/excluded are reachable from any active phase (a tender can drop out early). won/lost are only reachable from submission/follow-up, since a bid must actually be submitted first. Terminal statuses (won/lost/cancelled/not-evaluated/excluded) have no further transitions.

UI lives as a "Change status" row action in TendersTable (Filament Action, not EditAction) — its modal only offers the record's currently-valid next statuses and an optional reason, and the action hides itself once the tender is terminal. Don't re-add a free status Select to the form.
