<?php

namespace App\Filament\Resources\Tenders\Pages;

use App\Enums\DeadlineType;
use App\Enums\Right;
use App\Filament\Resources\Tenders\Schemas\TenderForm;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\Tender;
use Filament\Resources\Pages\CreateRecord;

class CreateTender extends CreateRecord
{
    protected static string $resource = TenderResource::class;

    /**
     * Captured in mutateFormDataBeforeCreate() before those keys are stripped from $data, and
     * used in afterCreate() below — calling $this->form->getState() a second time there would
     * interfere with the teamMembers Repeater's relationship save that already ran between the
     * two hooks (CreateRecord::create()'s saveRelationships() call).
     *
     * @var array{submission_deadline: mixed, bidder_question_deadline: mixed, site_visit_date: mixed}
     */
    private array $deadlineData;

    /**
     * Belt-and-braces server-side enforcement of the "see prices" right: even
     * though the price fields are hidden from users without it, never trust
     * that UI visibility alone kept a tampered request from smuggling a value
     * through — see .ai/rules/permissions.md.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->can(Right::SEE_PRICES->value)) {
            unset($data['estimated_contract_volume'], $data['estimated_contract_volume_unknown']);
        }

        if ($categoryId = auth()->user()?->service_category_id) {
            $data['service_category_id'] = $categoryId;
        }

        /**
         * Belt-and-braces per [[resources-tenders]]/[[permissions]]: the owner select is
         * disabled in the UI for anyone without team-assignment rights, but never trust that
         * alone — force the owner back to the creating user regardless of what was submitted.
         */
        if (! TenderForm::canManageTeam()) {
            $data['owner_id'] = auth()->id();
        }

        /**
         * submission_deadline/bidder_question_deadline/site_visit_date aren't Tender columns
         * any more — they're transient form state written into tender_deadlines in
         * afterCreate() below, mirroring UserResource's role/rights transient-field pattern.
         */
        $this->deadlineData = [
            'submission_deadline' => $data['submission_deadline'] ?? null,
            'bidder_question_deadline' => $data['bidder_question_deadline'] ?? null,
            'site_visit_date' => $data['site_visit_date'] ?? null,
        ];
        unset($data['submission_deadline'], $data['bidder_question_deadline'], $data['site_visit_date']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Tender $tender */
        $tender = $this->getRecord();

        $tender->upsertDeadline(DeadlineType::SUBMISSION, $this->deadlineData['submission_deadline']);
        $tender->upsertDeadline(DeadlineType::BIDDER_QUESTIONS, $this->deadlineData['bidder_question_deadline']);
        $tender->upsertDeadline(DeadlineType::SITE_VISIT, $this->deadlineData['site_visit_date']);
        $tender->syncBidValidityDeadline();
    }
}
