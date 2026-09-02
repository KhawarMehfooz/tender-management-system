---
paths:
  - app/Filament/Resources/Tenders/TenderResource.php
---

# Filament Resources Tenders

## Group tender relation-manager tabs with RelationGroup once the tab bar grows
TenderResource::getRelations() reached 13 top-level tabs by M9 and started overflowing the tab bar. Fixed by clustering related tabs under Filament\Resources\RelationManagers\RelationGroup::make($label, [...]) — the same mechanism already used for the 3-tab "Reference Library" group — rather than any custom CSS/scroll workaround. Current groups: "Engagement" (Communication, Site Visits, Document Requests) and "Closure" (Submission, Follow-up, Result, Lessons Learned), both under lang/en/tenders.php's relation_groups.* keys; "Reference Library" (References, Certificates, Concept Blocks) stays separate under lang/en/reference_library.php. Core workflow tabs (Tasks, Deadlines, Documents, Calculations, Bid Decision) stay top-level, ungrouped.

Don't chain ->icon(...) on these RelationGroups — none of the existing tabs/groups on this resource (including "Reference Library") carry one, and the user explicitly asked to keep it that way after an initial pass added icons to just the 2 new groups, which stuck out as inconsistent. Tab-level icons were also explicitly deferred for individual RelationManagers back in M8 (see milestones/m8-...md's final task) — this extends the same "no icons on tender-detail tabs" stance to RelationGroups.

When a future milestone adds another tender-detail tab: prefer folding it into one of these existing groups if it fits thematically, or forming a new RelationGroup, over leaving the top-level tab count to keep growing unchecked. Existing RelationManager tests (Livewire::test(SomeRelationManager::class, ['ownerRecord' => ..., 'pageClass' => ...])) are unaffected by grouping — they instantiate the RelationManager directly, not through getRelations()'s tab structure.
