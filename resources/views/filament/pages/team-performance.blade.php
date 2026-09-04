<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('team_performance.description') }}
    </p>

    <x-filament::section :heading="__('team_performance.departments.heading')">
        @if (empty($departmentBreakdown))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('team_performance.departments.no_data') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="divide-x divide-gray-200 dark:divide-white/10">
                            <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.departments.department') }}
                            </th>
                            @foreach (\App\Enums\TaskStatus::cases() as $status)
                                <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                    {{ $status->getLabel() }}
                                </th>
                            @endforeach
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.departments.total') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.departments.on_time_rate') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.departments.correction_loop_rate') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($departmentBreakdown as $row)
                            <tr class="divide-x divide-gray-200 dark:divide-white/10">
                                <td class="px-3 py-2 font-medium">{{ $row['label'] }}</td>
                                @foreach (\App\Enums\TaskStatus::cases() as $status)
                                    <td class="px-3 py-2 text-end">{{ $row['statusCounts'][$status->value] }}</td>
                                @endforeach
                                <td class="px-3 py-2 text-end">{{ $row['total'] }}</td>
                                <td class="px-3 py-2 text-end">
                                    {{ $row['onTimeRate'] === null ? __('team_performance.departments.no_rate') : number_format($row['onTimeRate'] * 100, 1) . '%' }}
                                </td>
                                <td class="px-3 py-2 text-end">
                                    {{ $row['correctionLoopRate'] === null ? __('team_performance.departments.no_rate') : number_format($row['correctionLoopRate'] * 100, 1) . '%' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('team_performance.bottleneck.heading')" :description="__('team_performance.bottleneck.description')">
        @if (empty($bottleneckBreakdown))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('team_performance.bottleneck.no_data') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="divide-x divide-gray-200 dark:divide-white/10">
                            <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.bottleneck.step') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.bottleneck.sample_size') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.bottleneck.average_duration_days') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($bottleneckBreakdown as $row)
                            <tr class="divide-x divide-gray-200 dark:divide-white/10">
                                <td class="px-3 py-2 font-medium">{{ $row['label'] }}</td>
                                <td class="px-3 py-2 text-end">{{ $row['sampleSize'] }}</td>
                                <td class="px-3 py-2 text-end">{{ number_format($row['averageDurationDays'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('team_performance.rankings.heading')" :description="__('team_performance.rankings.description')">
        @if (empty($rankings))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('team_performance.rankings.no_data') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="divide-x divide-gray-200 dark:divide-white/10">
                            <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.rankings.employee') }}
                            </th>
                            <th class="px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.rankings.department') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.rankings.score') }}
                            </th>
                            <th class="px-3 py-2 text-end font-medium text-gray-500 dark:text-gray-400">
                                {{ __('team_performance.rankings.win_rate') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rankings as $row)
                            <tr class="divide-x divide-gray-200 dark:divide-white/10">
                                <td class="px-3 py-2 font-medium">{{ $row['name'] }}</td>
                                <td class="px-3 py-2">{{ $row['department'] }}</td>
                                <td class="px-3 py-2 text-end">{{ number_format($row['score'], 1) }}</td>
                                <td class="px-3 py-2 text-end">
                                    {{ $row['winRate'] === null ? __('team_performance.rankings.no_rate') : number_format($row['winRate'] * 100, 0) . '%' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
