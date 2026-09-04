@php
    $overall = $score->score();
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm mb-6 dark:border-white/10 dark:bg-gray-900">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ __('tender_participation_scores.summary.heading') }}
        </h3>

        @if ($overall !== null)
            <x-filament::badge color="primary" size="lg">
                {{ $overall }} / 100
            </x-filament::badge>
        @else
            <x-filament::badge color="gray" size="lg">
                {{ __('tender_participation_scores.summary.incomplete', ['count' => $missingRatingsCount]) }}
            </x-filament::badge>
        @endif
    </div>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($manualFields as $field)
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('tender_participation_scores.factors.'.$field) }}
                </dt>
                <dd class="text-sm text-gray-950 dark:text-white">
                    {{ $score->{$field} ?? '—' }} / 5
                </dd>
            </div>
        @endforeach

        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ __('tender_participation_scores.factors.contract_value') }}
            </dt>
            <dd class="text-sm text-gray-950 dark:text-white">
                {{ $score->contractValueRating() }} / 5
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ __('tender_participation_scores.factors.expected_margin') }}
            </dt>
            <dd class="text-sm text-gray-950 dark:text-white">
                {{ $score->marginRating() }} / 5
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ __('tender_participation_scores.factors.past_win_rate') }}
            </dt>
            <dd class="text-sm text-gray-950 dark:text-white">
                {{ $score->pastWinRateRating() }} / 5
                <span class="italic text-gray-400 dark:text-gray-500">({{ __('tender_participation_scores.summary.unknown_note') }})</span>
            </dd>
        </div>
    </dl>
</div>
