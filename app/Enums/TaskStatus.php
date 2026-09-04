<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasLabel
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in-progress';
    case WAITING_ON_ANOTHER_TASK = 'waiting-on-another-task';
    case IN_REVIEW = 'in-review';
    case CORRECTION_REQUIRED = 'correction-required';
    case DONE = 'done';

    public function getLabel(): string
    {
        return __('tasks.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::DONE => 'success',
            self::CORRECTION_REQUIRED => 'danger',
            self::IN_REVIEW, self::WAITING_ON_ANOTHER_TASK => 'warning',
            default => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::DONE;
    }

    /**
     * Allowed next statuses: open work moves forward through progress/review, can pause on a
     * dependency, and a correction loop sends review feedback back to in-progress. Done is
     * terminal (mirrors TenderStatus::allowedTransitions()'s forward-with-review-loop shape).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::WAITING_ON_ANOTHER_TASK],
            self::WAITING_ON_ANOTHER_TASK => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::IN_REVIEW, self::WAITING_ON_ANOTHER_TASK],
            self::IN_REVIEW => [self::DONE, self::CORRECTION_REQUIRED],
            self::CORRECTION_REQUIRED => [self::IN_PROGRESS],
            self::DONE => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
