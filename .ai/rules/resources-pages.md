---
paths:
  - 'app/Filament/Resources/**/Pages/*.php'
---

# Resources Pages

## Never call $this->form->getState() a second time in afterCreate()/afterSave() when a relationship() field exists
CreateRecord::create()/EditRecord::save() call $this->form->getState() once internally to build $data for mutateFormDataBeforeCreate/Save, then save any relationship() components (e.g. a Repeater::make(...)->relationship()) using that same pass. Calling $this->form->getState() again yourself inside afterCreate()/afterSave() to read extra transient fields (e.g. fields stripped from $data because they don't map to a model column) silently wipes rows already written by a sibling relationship() field on the same form — no exception, the record and other columns save fine, only the relationship rows go missing.

Confirmed while wiring TenderResource's transient submission_deadline/bidder_question_deadline/site_visit_date fields (into tender_deadlines) alongside the existing teamMembers Repeater — calling getState() in afterCreate() silently dropped every TenderTeamMember row.

Fix: capture whatever extra data you need on a private property inside mutateFormDataBeforeCreate()/mutateFormDataBeforeSave() (before you unset those keys from $data), then read that property in afterCreate()/afterSave() instead of re-calling getState(). See CreateTender/EditTender for the reference implementation. This has no effect on forms with no relationship() fields (e.g. UserResource's role/rights afterCreate() calling getState() is safe there), but treat any second getState() call as risky by default.
