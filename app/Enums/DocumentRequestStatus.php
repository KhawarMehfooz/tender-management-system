<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentRequestStatus: string implements HasLabel
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in-progress';
    case FULFILLED = 'fulfilled';
    case WITHDRAWN = 'withdrawn';

    public function getLabel(): string
    {
        return __('document_request_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::FULFILLED => 'success',
            self::WITHDRAWN => 'gray',
            self::IN_PROGRESS => 'warning',
            self::OPEN => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::FULFILLED, self::WITHDRAWN => true,
            self::OPEN, self::IN_PROGRESS => false,
        };
    }

    /**
     * Open work can move to in-progress or resolve directly (fulfilled/withdrawn); in-progress
     * can also resolve either way; terminal statuses have no further transitions — mirrors
     * TaskStatus::allowedTransitions()'s shape.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN => [self::IN_PROGRESS, self::FULFILLED, self::WITHDRAWN],
            self::IN_PROGRESS => [self::FULFILLED, self::WITHDRAWN],
            self::FULFILLED, self::WITHDRAWN => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
