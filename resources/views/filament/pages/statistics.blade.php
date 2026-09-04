<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('statistics.description') }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.formal_exclusions') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $formalExclusions['count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                {{ $formalExclusions['count'] }}
                @if ($formalExclusions['rate'] !== null)
                    <span class="text-base font-normal text-gray-500 dark:text-gray-400">({{ number_format($formalExclusions['rate'] * 100, 1) }}%)</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('statistics.kpi.formal_exclusions_hint') }}</p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.win_rate') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                {{ $winRate === null ? __('statistics.no_rate') : number_format($winRate * 100, 1) . '%' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.participation_rate') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                {{ $participationRate === null ? __('statistics.no_rate') : number_format($participationRate * 100, 1) . '%' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.bid_volume') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $bidVolume['count'] }}</p>
            @if ($canSeePrices && $bidVolume['volume'] !== null)
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('tenders.infolist.money_eur', ['amount' => number_format($bidVolume['volume'], 2)]) }}</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.won_volume') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                @if (! $canSeePrices)
                    <span class="text-sm font-normal text-gray-400">{{ __('statistics.price_hidden') }}</span>
                @else
                    {{ __('tenders.infolist.money_eur', ['amount' => number_format($wonLostVolume['wonVolume'] ?? 0, 2)]) }}
                @endif
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.lost_volume') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                @if (! $canSeePrices)
                    <span class="text-sm font-normal text-gray-400">{{ __('statistics.price_hidden') }}</span>
                @else
                    {{ __('tenders.infolist.money_eur', ['amount' => number_format($wonLostVolume['lostVolume'] ?? 0, 2)]) }}
                @endif
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.average_contract_value') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                @if (! $canSeePrices)
                    <span class="text-sm font-normal text-gray-400">{{ __('statistics.price_hidden') }}</span>
                @elseif ($averageContractValue === null)
                    {{ __('statistics.no_rate') }}
                @else
                    {{ __('tenders.infolist.money_eur', ['amount' => number_format($averageContractValue, 2)]) }}
                @endif
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.average_margin') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                @if (! $canSeePrices)
                    <span class="text-sm font-normal text-gray-400">{{ __('statistics.price_hidden') }}</span>
                @elseif ($averageMargin === null)
                    {{ __('statistics.no_rate') }}
                @else
                    {{ number_format($averageMargin, 1) }}%
                @endif
            </p>
        </x-filament::section>

        <x-filament::section>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.kpi.average_handling_time') }}</p>
            <p class="mt-1 text-2xl font-semibold">
                {{ $averageHandlingTimeDays === null ? __('statistics.no_rate') : __('statistics.kpi.days', ['count' => number_format($averageHandlingTimeDays, 1)]) }}
            </p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('statistics.deadline_reliability.heading')" :description="__('statistics.deadline_reliability.description')">
        @if ($deadlineReliability['onTimeRate'] === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('statistics.deadline_reliability.no_data') }}</p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.deadline_reliability.on_time_rate') }}</p>
                    <p class="mt-1 text-xl font-semibold">{{ number_format($deadlineReliability['onTimeRate'] * 100, 1) }}%</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.deadline_reliability.average_days_ahead') }}</p>
                    <p class="mt-1 text-xl font-semibold">{{ number_format($deadlineReliability['averageDaysAhead'], 1) }}</p>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('statistics.loss_reasons.heading')" :description="__('statistics.loss_reasons.description')">
        @if (empty($lossReasons) || collect($lossReasons)->sum('count') === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('statistics.loss_reasons.no_data') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="divide-x divide-gray-200 dark:divide-white/10">
                            <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.loss_reasons.reason') }}</th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.loss_reasons.count') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($lossReasons as $row)
                            @if ($row['count'] > 0)
                                <tr class="divide-x divide-gray-200 dark:divide-white/10">
                                    <td class="px-3 py-2 font-medium">{{ $row['reason'] }}</td>
                                    <td class="px-3 py-2 text-end">{{ $row['count'] }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('statistics.development.heading')" :description="__('statistics.development.description')">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="divide-x divide-gray-200 dark:divide-white/10">
                        <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.development.quarter') }}</th>
                        <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.development.average_bid_price') }}</th>
                        <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.development.we_won') }}</th>
                        <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">{{ __('statistics.development.they_won') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($priceCompetitorDevelopment as $row)
                        <tr class="divide-x divide-gray-200 dark:divide-white/10">
                            <td class="px-3 py-2 font-medium">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-end">
                                @if (! $canSeePrices)
                                    <span class="text-xs text-gray-400">{{ __('statistics.price_hidden') }}</span>
                                @elseif ($row['averageBidPrice'] === null)
                                    {{ __('statistics.no_rate') }}
                                @else
                                    {{ __('tenders.infolist.money_eur', ['amount' => number_format($row['averageBidPrice'], 2)]) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-end">{{ $row['weWon'] }}</td>
                            <td class="px-3 py-2 text-end">{{ $row['theyWon'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
