<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenderStatus: string implements HasLabel
{
    case INTAKE = 'intake';
    case REVIEW = 'review';
    case DECISION = 'decision';
    case PROCESSING = 'processing';
    case QUALITY = 'quality';
    case SUBMISSION = 'submission';
    case FOLLOW_UP = 'follow-up';
    case WON = 'won';
    case LOST = 'lost';
    case CANCELLED = 'cancelled';
    case NOT_EVALUATED = 'not-evaluated';
    case EXCLUDED = 'excluded';

    public function getLabel(): string
    {
        return __('tenders.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::WON => 'success',
            self::LOST, self::EXCLUDED => 'danger',
            default => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::WON, self::LOST, self::CANCELLED, self::NOT_EVALUATED, self::EXCLUDED => true,
            default => false,
        };
    }

    /**
     * Allowed next statuses per idea.md's 8-phase workflow: the 7 active phases progress
     * linearly (no skipping, no going back), and cancelled/not-evaluated/excluded can end
     * the tender from any active phase. Won/lost only make sense once a bid has actually
     * been submitted, so they're only reachable from submission/follow-up. Terminal states
     * have no further transitions.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        $exitEarly = [self::CANCELLED, self::NOT_EVALUATED, self::EXCLUDED];
        $exitAfterSubmission = [self::WON, self::LOST, ...$exitEarly];

        return match ($this) {
            self::INTAKE => [self::REVIEW, ...$exitEarly],
            self::REVIEW => [self::DECISION, ...$exitEarly],
            self::DECISION => [self::PROCESSING, ...$exitEarly],
            self::PROCESSING => [self::QUALITY, ...$exitEarly],
            self::QUALITY => [self::SUBMISSION, ...$exitEarly],
            self::SUBMISSION => [self::FOLLOW_UP, ...$exitAfterSubmission],
            self::FOLLOW_UP => $exitAfterSubmission,
            default => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
